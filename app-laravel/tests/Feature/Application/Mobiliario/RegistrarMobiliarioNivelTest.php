<?php

namespace Tests\Feature\Application\Mobiliario;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\PrecondicionIncumplida;
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
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class RegistrarMobiliarioNivelTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private function crearEscuelaNivel(string $claveNivel): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new MobiliarioConceptosSeeder)->run();

        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        // WS-2.4b: RegistrarMobiliarioNivel ahora exige Paso 2 completo antes de escribir.
        $this->completarPaso2($escuela->id);
        $nivel = NivelEducativo::where('clave', $claveNivel)->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        // WS-2.4b: Mobiliario solo es alcanzable con Inmueble e Infraestructura completados.
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'infraestructura');

        return $escuelaNivel;
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

    // WS-2.4b — un caller que se salte CompuertaPaso3 no debe poder escribir
    // mobiliario si Paso 2 no está completo.
    public function test_rechaza_registrar_sin_paso_2_completo(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new MobiliarioConceptosSeeder)->run();

        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 2', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $nivel = NivelEducativo::where('clave', 'inicial')->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        // Sin Paso 2 completo, ni el orden de Paso 3 se cumple tampoco.

        $this->expectException(PrecondicionIncumplida::class);

        app(RegistrarMobiliarioNivel::class)->ejecutar($escuelaNivel->id, $this->dosConceptos());
    }

    // WS-2.4b — con Paso 2 completo pero sin 'infraestructura' marcada, el
    // orden de Paso 3 sigue rechazando la escritura.
    public function test_rechaza_registrar_cuando_infraestructura_no_esta_completada(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new MobiliarioConceptosSeeder)->run();

        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 3', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->completarPaso2($escuela->id);
        $nivel = NivelEducativo::where('clave', 'inicial')->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');
        // Deliberadamente NO se llama a MarcarPasoCompletado('infraestructura').

        $this->expectException(PrecondicionIncumplida::class);

        app(RegistrarMobiliarioNivel::class)->ejecutar($escuelaNivel->id, $this->dosConceptos());
    }
}
