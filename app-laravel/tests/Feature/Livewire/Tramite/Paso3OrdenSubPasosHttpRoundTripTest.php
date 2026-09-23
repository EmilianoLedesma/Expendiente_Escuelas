<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

/**
 * WS-1.3: un sub-paso de Paso 3 solo es alcanzable si el anterior está
 * completado; si no, la página redirige al primer sub-paso pendiente, antes
 * de cualquier efecto (auto-completado incluido). Round-trip HTTP real (ADR-003).
 */
class Paso3OrdenSubPasosHttpRoundTripTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private Solicitante $solicitante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->solicitante = Solicitante::factory()->create();
    }

    private function escuelaNivel(string $claveNivel, string ...$completados): EscuelaNivel
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $this->solicitante->id]);
        $this->completarPaso2($escuela->id);
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $claveNivel)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        foreach ($completados as $clave) {
            (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, $clave);
        }

        return $escuelaNivel;
    }

    private function get3(string $pagina, EscuelaNivel $escuelaNivel): TestResponse
    {
        return $this->actingAs($this->solicitante->user)
            ->get(route("tramite.paso3-{$pagina}", ['escuelaNivel' => $escuelaNivel->id]));
    }

    public function test_infraestructura_sin_inmueble_redirige_a_inmueble(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        $this->get3('infraestructura', $escuelaNivel)
            ->assertRedirect(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]));
    }

    public function test_infraestructura_con_inmueble_completado_se_muestra(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria', 'inmueble');

        $this->get3('infraestructura', $escuelaNivel)
            ->assertOk()
            ->assertSeeLivewire('tramite.paso3.infraestructura-nivel');
    }

    public function test_mobiliario_sin_nada_capturado_redirige_al_primer_pendiente(): void
    {
        $escuelaNivel = $this->escuelaNivel('inicial');

        $this->get3('mobiliario', $escuelaNivel)
            ->assertRedirect(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]));
    }

    public function test_mobiliario_inicial_con_infraestructura_completada_se_muestra(): void
    {
        $escuelaNivel = $this->escuelaNivel('inicial', 'inmueble', 'infraestructura');

        $this->get3('mobiliario', $escuelaNivel)
            ->assertOk()
            ->assertSeeLivewire('tramite.paso3.mobiliario-nivel');
    }

    /** El auto-completado de Mobiliario (nivel ≠ Inicial) no puede saltarse Infraestructura. */
    public function test_mobiliario_no_inicial_con_infraestructura_pendiente_redirige_y_no_escribe(): void
    {
        $escuelaNivel = $this->escuelaNivel('preescolar', 'inmueble');

        $this->get3('mobiliario', $escuelaNivel)
            ->assertRedirect(route('tramite.paso3-infraestructura', ['escuelaNivel' => $escuelaNivel->id]));

        $this->assertDatabaseMissing('escuela_nivel_pasos', [
            'escuela_nivel_id' => $escuelaNivel->id,
            'paso_captura_id' => DB::table('pasos_captura')->where('clave', 'mobiliario')->value('id'),
        ]);
        $this->assertDatabaseCount('escuela_nivel_pasos', 1);
    }

    public function test_mobiliario_no_inicial_con_infraestructura_completada_se_auto_completa(): void
    {
        $escuelaNivel = $this->escuelaNivel('preescolar', 'inmueble', 'infraestructura');

        $this->get3('mobiliario', $escuelaNivel)
            ->assertRedirect(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $escuelaNivel->id]));

        $this->assertDatabaseHas('escuela_nivel_pasos', [
            'escuela_nivel_id' => $escuelaNivel->id,
            'paso_captura_id' => DB::table('pasos_captura')->where('clave', 'mobiliario')->value('id'),
            'estado' => 'completado',
        ]);
    }

    public function test_proximos_pasos_con_mobiliario_pendiente_redirige_a_mobiliario(): void
    {
        $escuelaNivel = $this->escuelaNivel('inicial', 'inmueble', 'infraestructura');

        $this->get3('proximos-pasos', $escuelaNivel)
            ->assertRedirect(route('tramite.paso3-mobiliario', ['escuelaNivel' => $escuelaNivel->id]));
    }

    public function test_proximos_pasos_con_los_sub_pasos_construidos_completos_se_muestra(): void
    {
        $escuelaNivel = $this->escuelaNivel('inicial', 'inmueble', 'infraestructura', 'mobiliario');

        $this->get3('proximos-pasos', $escuelaNivel)
            ->assertOk()
            ->assertSeeLivewire('tramite.paso3-proximos-pasos');
    }

    /** Volver atrás a un sub-paso ya completado sigue permitido. */
    public function test_inmueble_siempre_es_alcanzable(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria', 'inmueble', 'infraestructura');

        $this->get3('inmueble', $escuelaNivel)->assertOk()->assertSeeLivewire('tramite.paso3.datos-inmueble');
    }
}
