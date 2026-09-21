<?php

namespace Tests\Feature\Application\Mobiliario;

use App\Application\Mobiliario\RegistrarMobiliarioNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\MobiliarioConceptosSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class RegistrarMobiliarioNivelTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuelaNivel(string $claveNivel): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new MobiliarioConceptosSeeder)->run();

        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $nivel = NivelEducativo::where('clave', $claveNivel)->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    /** @return array<int, int> */
    private function dosConceptos(): array
    {
        $ids = DB::table('mobiliario_conceptos')->orderBy('id')->limit(2)->pluck('id')->all();

        return [(int) $ids[0] => 4, (int) $ids[1] => 7];
    }

    public function test_guarda_las_cantidades_declaradas(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel('inicial');
        $cantidades = $this->dosConceptos();

        app(RegistrarMobiliarioNivel::class)->ejecutar($escuelaNivel->id, $cantidades);

        foreach ($cantidades as $conceptoId => $cantidad) {
            $this->assertDatabaseHas('mobiliario_nivel', [
                'escuela_nivel_id' => $escuelaNivel->id,
                'concepto_id' => $conceptoId,
                'cantidad_declarada' => $cantidad,
            ]);
        }
    }

    public function test_marca_el_paso_mobiliario_como_completado(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel('inicial');

        app(RegistrarMobiliarioNivel::class)->ejecutar($escuelaNivel->id, $this->dosConceptos());

        $pasoId = DB::table('pasos_captura')->where('clave', 'mobiliario')->value('id');
        $this->assertDatabaseHas('escuela_nivel_pasos', [
            'escuela_nivel_id' => $escuelaNivel->id,
            'paso_captura_id' => $pasoId,
            'estado' => 'completado',
        ]);
    }

    public function test_reenviar_el_formulario_actualiza_en_vez_de_duplicar(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel('inicial');
        $cantidades = $this->dosConceptos();
        $primerConcepto = (int) array_key_first($cantidades);

        app(RegistrarMobiliarioNivel::class)->ejecutar($escuelaNivel->id, $cantidades);
        $cantidades[$primerConcepto] = 99;
        app(RegistrarMobiliarioNivel::class)->ejecutar($escuelaNivel->id, $cantidades);

        $this->assertSame(2, DB::table('mobiliario_nivel')->where('escuela_nivel_id', $escuelaNivel->id)->count());
        $this->assertDatabaseHas('mobiliario_nivel', [
            'escuela_nivel_id' => $escuelaNivel->id,
            'concepto_id' => $primerConcepto,
            'cantidad_declarada' => 99,
        ]);
    }

    public function test_rechaza_un_nivel_distinto_de_inicial(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel('preescolar');

        $this->expectException(InvalidArgumentException::class);

        app(RegistrarMobiliarioNivel::class)->ejecutar($escuelaNivel->id, $this->dosConceptos());
    }

    public function test_rechaza_un_concepto_inexistente(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel('inicial');

        $this->expectException(InvalidArgumentException::class);

        app(RegistrarMobiliarioNivel::class)->ejecutar($escuelaNivel->id, [999999 => 1]);
    }

    public function test_rechaza_una_cantidad_negativa(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel('inicial');
        $conceptoId = (int) DB::table('mobiliario_conceptos')->orderBy('id')->value('id');

        $this->expectException(InvalidArgumentException::class);

        app(RegistrarMobiliarioNivel::class)->ejecutar($escuelaNivel->id, [$conceptoId => -1]);
    }

    public function test_un_envio_vacio_no_marca_el_paso(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel('inicial');

        $this->expectException(InvalidArgumentException::class);

        app(RegistrarMobiliarioNivel::class)->ejecutar($escuelaNivel->id, []);
    }
}
