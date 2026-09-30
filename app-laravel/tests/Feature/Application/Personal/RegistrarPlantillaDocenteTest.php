<?php

namespace Tests\Feature\Application\Personal;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Personal\DTO\DatosPersona;
use App\Application\Personal\RegistrarPlantillaDocente;
use App\Models\Personal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class RegistrarPlantillaDocenteTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function persona(int $cargoId, string $nombre = 'Ana López', ?int $salaId = null, ?int $asignaturaId = null): DatosPersona
    {
        return new DatosPersona(
            cargoPuestoId: $cargoId, nombre: $nombre, nacionalidad: 'Mexicana', sexo: 'F',
            estudios: 'Lic. en Educación Primaria', cedulaODocumento: '1234567', salaId: $salaId, asignaturaId: $asignaturaId,
        );
    }

    public function test_guarda_la_plantilla_y_completa_el_paso(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plantilla_docente');

        app(RegistrarPlantillaDocente::class)->ejecutar($escuelaNivel->id, [
            $this->persona($this->cargoId('primaria', 'Director Técnico'), 'Ana López'),
            $this->persona($this->cargoId('primaria', 'Docente Titular de Grupo'), 'Luis Pérez'),
        ]);

        $this->assertSame(['Ana López', 'Luis Pérez'], Personal::where('escuela_nivel_id', $escuelaNivel->id)->orderBy('id')->pluck('nombre')->all());
        $this->assertTrue($this->pasoCompletado($escuelaNivel, 'plantilla_docente'));
    }

    public function test_volver_a_guardar_reemplaza_la_plantilla_anterior(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plantilla_docente');
        $registrar = app(RegistrarPlantillaDocente::class);
        $director = $this->cargoId('primaria', 'Director Técnico');

        $registrar->ejecutar($escuelaNivel->id, [$this->persona($director, 'Ana López'), $this->persona($director, 'Otra')]);
        $registrar->ejecutar($escuelaNivel->id, [$this->persona($director, 'Ana López')]);

        $this->assertSame(1, Personal::where('escuela_nivel_id', $escuelaNivel->id)->count());
    }

    public function test_inicial_asigna_sala_a_responsables_y_asistentes(): void
    {
        $escuelaNivel = $this->nivelListoPara('inicial', 'plantilla_docente');
        $salaId = (int) DB::table('salas')->where('clave', 'lactantes_a')->value('id');

        app(RegistrarPlantillaDocente::class)->ejecutar($escuelaNivel->id, [
            $this->persona($this->cargoId('inicial', 'Asistente Educativo'), salaId: $salaId),
        ]);

        $personal = Personal::where('escuela_nivel_id', $escuelaNivel->id)->sole();
        $this->assertDatabaseHas('personal_salas', ['personal_id' => $personal->id, 'sala_id' => $salaId]);
    }

    public function test_secundaria_asigna_la_asignatura_del_docente_titular(): void
    {
        $escuelaNivel = $this->nivelListoPara('secundaria', 'plantilla_docente');
        $asignaturaId = (int) DB::table('asignaturas')->where('nombre', 'Educación Física')->value('id');

        app(RegistrarPlantillaDocente::class)->ejecutar($escuelaNivel->id, [
            $this->persona($this->cargoId('secundaria', 'Docente Titular'), asignaturaId: $asignaturaId),
        ]);

        $personal = Personal::where('escuela_nivel_id', $escuelaNivel->id)->sole();
        $this->assertDatabaseHas('personal_asignaturas', ['personal_id' => $personal->id, 'asignatura_id' => $asignaturaId]);
    }

    public function test_exige_sala_o_asignatura_cuando_el_cargo_la_requiere(): void
    {
        $inicial = $this->nivelListoPara('inicial', 'plantilla_docente');

        try {
            app(RegistrarPlantillaDocente::class)->ejecutar($inicial->id, [
                $this->persona($this->cargoId('inicial', 'Director Técnico')),
                $this->persona($this->cargoId('inicial', 'Responsable de Sala')),
            ]);
            $this->fail('Se esperaba DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertSame(['personas.1.salaId'], array_keys($e->errores));
        }

        $this->assertSame(0, Personal::count());
    }

    public function test_rechaza_cargos_de_otro_nivel_campos_vacios_y_sexo_invalido(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plantilla_docente');
        $vacia = new DatosPersona(cargoPuestoId: $this->cargoId('secundaria', 'Prefecto'), nombre: ' ', nacionalidad: 'Mexicana', sexo: 'X', estudios: 'Lic.', cedulaODocumento: '1');

        try {
            app(RegistrarPlantillaDocente::class)->ejecutar($escuelaNivel->id, [$vacia]);
            $this->fail('Se esperaba DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertEqualsCanonicalizing(['personas.0.cargoPuestoId', 'personas.0.nombre', 'personas.0.sexo'], array_keys($e->errores));
        }
    }

    public function test_exige_al_menos_una_persona(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plantilla_docente');

        $this->expectException(DatosInvalidos::class);

        app(RegistrarPlantillaDocente::class)->ejecutar($escuelaNivel->id, []);
    }

    public function test_no_se_puede_saltar_el_orden_del_wizard(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');

        $this->expectException(PrecondicionIncumplida::class);

        app(RegistrarPlantillaDocente::class)->ejecutar($escuelaNivel->id, [$this->persona($this->cargoId('primaria', 'Director Técnico'))]);
    }
}
