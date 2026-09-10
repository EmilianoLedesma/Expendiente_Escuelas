<?php

namespace Tests\Feature\Application\EscuelaNiveles;

use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Models\Escuela;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class RegistrarNivelesSeleccionadosTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_crea_un_escuela_nivel_por_cada_nivel_seleccionado(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $escuela = $this->crearEscuela();
        $preescolar = NivelEducativo::where('clave', 'preescolar')->first();
        $primaria = NivelEducativo::where('clave', 'primaria')->first();
        $estadoEnCaptura = DB::table('estados_expediente')->where('clave', 'en_captura')->first();

        (new RegistrarNivelesSeleccionados)->ejecutar($escuela->id, [$preescolar->id, $primaria->id]);

        $this->assertDatabaseCount('escuela_niveles', 2);
        $this->assertDatabaseHas('escuela_niveles', [
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $preescolar->id,
            'tipo_tramite' => 'alta_nueva',
            'estado_id' => $estadoEnCaptura->id,
        ]);
        $this->assertDatabaseHas('escuela_niveles', [
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $primaria->id,
            'tipo_tramite' => 'alta_nueva',
            'estado_id' => $estadoEnCaptura->id,
        ]);
    }

    public function test_rechaza_una_lista_vacia_de_niveles(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $escuela = $this->crearEscuela();

        $this->expectException(InvalidArgumentException::class);

        (new RegistrarNivelesSeleccionados)->ejecutar($escuela->id, []);
    }

    public function test_rechaza_un_nivel_fuera_de_educacion_basica(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $escuela = $this->crearEscuela();
        $mediaSuperior = NivelEducativo::where('clave', 'media_superior')->first();

        $this->expectException(InvalidArgumentException::class);

        (new RegistrarNivelesSeleccionados)->ejecutar($escuela->id, [$mediaSuperior->id]);

        $this->assertDatabaseCount('escuela_niveles', 0);
    }

    public function test_un_segundo_envio_con_el_mismo_nivel_es_un_no_op(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $escuela = $this->crearEscuela();
        $preescolar = NivelEducativo::where('clave', 'preescolar')->first();

        (new RegistrarNivelesSeleccionados)->ejecutar($escuela->id, [$preescolar->id]);
        (new RegistrarNivelesSeleccionados)->ejecutar($escuela->id, [$preescolar->id]);

        $this->assertDatabaseCount('escuela_niveles', 1);
    }
}
