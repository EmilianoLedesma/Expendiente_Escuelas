<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class Paso3ProximosPasosTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private function crearEscuelaNivelPara(Solicitante $solicitante): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->completarPaso2($escuela->id);
        $nivel = NivelEducativo::where('clave', 'primaria')->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        // WS-1.3: el aterrizaje solo es alcanzable con los sub-pasos construidos completados.
        foreach (['inmueble', 'infraestructura', 'mobiliario'] as $paso) {
            (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, $paso);
        }

        return $escuelaNivel;
    }

    public function test_el_dueno_ve_el_aterrizaje(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivelPara($solicitante);

        $response = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertOk();
        $response->assertSee('el resto de la captura está pendiente', false);
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $dueno = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivelPara($dueno);
        $otro = Solicitante::factory()->create();

        $response = $this->actingAs($otro->user)
            ->get(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertForbidden();
    }

    /**
     * Defecto vivo que este trabajo cierra: ningún componente pasaba
     * escuelaNivelId al layout, así que el widget de progreso mostraba los 6
     * sub-pasos como "pendiente" incondicionalmente.
     */
    public function test_el_widget_de_progreso_refleja_los_pasos_completados(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivelPara($solicitante);

        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'infraestructura');
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'mobiliario');

        $response = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertOk();
        $html = $response->getContent();

        // 9 etapas en total ahora (Preregistro/Responsable/Documentos + los 6 de
        // Paso 3): Preregistro y Documentos también leen "completado" aquí —
        // ambos se derivan por existencia (escuela y escuela_niveles), y esta
        // escuela ya tiene ambos — y desde WS-1.2 el fixture también captura
        // el Responsable legal (precondición para entrar a Paso 3), así que las
        // 3 etapas de Paso 1-2 más los 3 sub-pasos de Paso 3 leen completado.
        $this->assertSame(6, substr_count($html, 'data-estado="completado"'));
        $this->assertSame(3, substr_count($html, 'data-estado="pendiente"'));
    }

    public function test_muestra_enlace_a_un_segundo_nivel_de_la_misma_escuela_que_aun_no_termina(): void
    {
        $solicitante = Solicitante::factory()->create();
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->completarPaso2($escuela->id);
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $primaria = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        $inicial = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'inicial')->value('id'),
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);

        (new MarcarPasoCompletado)->ejecutar($primaria->id, 'inmueble');
        (new MarcarPasoCompletado)->ejecutar($primaria->id, 'infraestructura');
        (new MarcarPasoCompletado)->ejecutar($primaria->id, 'mobiliario');

        $response = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $primaria->id]));

        $response->assertOk();
        $response->assertSee(route('tramite.paso3-inmueble', ['escuelaNivel' => $inicial->id]), false);
    }

    public function test_un_segundo_nivel_ya_completo_no_muestra_enlace_de_captura(): void
    {
        $solicitante = Solicitante::factory()->create();
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->completarPaso2($escuela->id);
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $primaria = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        $inicial = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'inicial')->value('id'),
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);

        foreach (['inmueble', 'infraestructura', 'mobiliario'] as $paso) {
            (new MarcarPasoCompletado)->ejecutar($primaria->id, $paso);
            (new MarcarPasoCompletado)->ejecutar($inicial->id, $paso);
        }

        $response = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $primaria->id]));

        $response->assertOk();
        $response->assertDontSee(route('tramite.paso3-inmueble', ['escuelaNivel' => $inicial->id]), false);
    }
}
