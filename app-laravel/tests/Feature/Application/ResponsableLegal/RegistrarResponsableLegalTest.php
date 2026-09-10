<?php

namespace Tests\Feature\Application\ResponsableLegal;

use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RegistrarResponsableLegalTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_tipo_fisica_crea_responsable_y_persona_fisica(): void
    {
        $escuela = $this->crearEscuela();

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica',
            nombre: 'Juana Pérez',
            rfc: 'PEJU800101ABC',
        ));

        $this->assertDatabaseHas('responsables_legales', ['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);
        $this->assertDatabaseHas('personas_fisicas', ['nombre' => 'Juana Pérez', 'rfc' => 'PEJU800101ABC']);
        $this->assertDatabaseCount('personas_morales', 0);
        $this->assertDatabaseCount('gestores', 0);
    }

    public function test_tipo_fisica_con_gestor_crea_los_tres_registros(): void
    {
        $escuela = $this->crearEscuela();

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica_con_gestor',
            nombre: 'Juana Pérez',
            gestorNombre: 'Carlos Gómez',
            gestorNumeroPoder: 'NP-100',
        ));

        $this->assertDatabaseHas('personas_fisicas', ['nombre' => 'Juana Pérez']);
        $this->assertDatabaseHas('gestores', ['nombre' => 'Carlos Gómez', 'numero_poder' => 'NP-100']);
    }

    public function test_tipo_moral_crea_responsable_y_persona_moral(): void
    {
        $escuela = $this->crearEscuela();

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'moral',
            razonSocial: 'Colegio Reforma S.C.',
            nombreRepresentanteLegal: 'Miguel Torres',
        ));

        $this->assertDatabaseHas('responsables_legales', ['escuela_id' => $escuela->id, 'tipo_persona' => 'moral']);
        $this->assertDatabaseHas('personas_morales', ['razon_social' => 'Colegio Reforma S.C.', 'nombre_representante_legal' => 'Miguel Torres']);
        $this->assertDatabaseCount('personas_fisicas', 0);
    }

    public function test_guarda_la_terna_de_nombres_propuestos(): void
    {
        $escuela = $this->crearEscuela();

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica',
            nombre: 'Juana Pérez',
            nombrePropuesto1: 'Colegio Reforma',
            nombrePropuesto2: 'Instituto Reforma',
            nombrePropuesto3: 'Escuela Reforma',
        ));

        $this->assertDatabaseHas('ternas_nombres', ['escuela_id' => $escuela->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Reforma']);
        $this->assertDatabaseHas('ternas_nombres', ['escuela_id' => $escuela->id, 'numero_propuesta' => 2, 'nombre_propuesto' => 'Instituto Reforma']);
        $this->assertDatabaseHas('ternas_nombres', ['escuela_id' => $escuela->id, 'numero_propuesta' => 3, 'nombre_propuesto' => 'Escuela Reforma']);
        $this->assertDatabaseCount('ternas_nombres', 3);
    }

    public function test_un_segundo_envio_no_duplica_la_terna_de_nombres(): void
    {
        $escuela = $this->crearEscuela();
        $datos = new DatosResponsableLegal(
            tipoPersona: 'fisica',
            nombre: 'Juana Pérez',
            nombrePropuesto1: 'Colegio Reforma',
            nombrePropuesto2: 'Instituto Reforma',
            nombrePropuesto3: 'Escuela Reforma',
        );

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $datos);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $datos);

        $this->assertDatabaseCount('ternas_nombres', 3);
    }

    public function test_tipo_desconocido_lanza_excepcion_sin_escribir_nada(): void
    {
        $escuela = $this->crearEscuela();

        try {
            (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'invalido'));
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertDatabaseCount('responsables_legales', 0);
        }
    }

    public function test_un_segundo_envio_para_la_misma_escuela_es_un_no_op(): void
    {
        $escuela = $this->crearEscuela();
        $datos = new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez');

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $datos);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $datos);

        $this->assertDatabaseCount('responsables_legales', 1);
        $this->assertDatabaseCount('personas_fisicas', 1);
    }
}
