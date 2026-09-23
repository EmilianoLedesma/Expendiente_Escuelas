<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Paso2ResponsableHttpRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_paso2_responde_200_por_http_real(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        $response = $this->actingAs($solicitante->user)->get(route('tramite.paso2', ['escuela' => $escuela->id]));

        $response->assertOk();
        $response->assertSeeLivewire('tramite.paso2-responsable');
    }

    /** Crea una escuela con un escuela_niveles "atorado" (seleccionado antes de WS-1.2, sin Paso 2 completo). */
    private function escuelaAtorada(Solicitante $solicitante): Escuela
    {
        $this->seed(DatabaseSeeder::class);
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'preescolar')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        return $escuela;
    }

    public function test_escuela_atorada_sin_responsable_muestra_el_formulario_de_responsable_no_paso3(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuelaAtorada($solicitante);

        $response = $this->actingAs($solicitante->user)->get(route('tramite.paso2', ['escuela' => $escuela->id]));

        $response->assertOk();
        $response->assertSeeLivewire('tramite.paso2-responsable');
    }

    public function test_escuela_atorada_con_responsable_sin_documentos_va_a_documentos_no_paso3(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuelaAtorada($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));

        $response = $this->actingAs($solicitante->user)->get(route('tramite.paso2', ['escuela' => $escuela->id]));

        $response->assertRedirect(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));
    }

    /**
     * Seguimiento de WS-1.4: can:update se re-aplica en POST /livewire/update
     * (middleware persistente de Livewire), no solo en el GET inicial. Un no-dueño
     * que reenvía el snapshot del dueño recibe 403; el dueño, con el mismo payload, 200.
     */
    public function test_un_no_dueno_recibe_403_al_reenviar_el_snapshot_por_livewire_update(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        $html = $this->actingAs($solicitante->user)->get(route('tramite.paso2', ['escuela' => $escuela->id]))->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]*)"/', $html, $match);
        $this->assertNotEmpty($match, 'No se encontró wire:snapshot en la página.');

        $update = fn () => $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => html_entity_decode($match[1]),
                'updates' => ['domicilioNotificaciones' => 'Calle ajena 1'],
                'calls' => [],
            ]],
        ]);

        $this->actingAs(Solicitante::factory()->create()->user);
        $update()->assertForbidden();

        $this->actingAs($solicitante->user);
        $update()->assertOk();
    }
}
