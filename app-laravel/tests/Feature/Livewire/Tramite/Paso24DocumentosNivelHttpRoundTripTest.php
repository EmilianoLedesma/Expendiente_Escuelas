<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

/**
 * Round-trip HTTP real a /livewire/update para la escritura de Paso 2.4
 * (ADR-003: Livewire::test() nunca ejercita el transporte real). La subida de
 * archivos va por el endpoint firmado de Livewire y queda cubierta por
 * Livewire::test() + las pruebas del caso de uso, igual que en WS-2.4.
 */
class Paso24DocumentosNivelHttpRoundTripTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    public function test_guardar_datos_por_http_real_persiste_turno_y_tipo_de_alumnado(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->completarPaso2($escuela->id);
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        $page = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $escuelaNivel->id]));
        $page->assertOk();
        $html = $page->getContent();

        preg_match('/name="csrf-token" content="([^"]+)"/', $html, $csrfMatch);
        $this->assertNotEmpty($csrfMatch, 'No se encontró el meta csrf-token en la página.');
        preg_match('/wire:snapshot="([^"]*)"/', $html, $snapshotMatch);
        $this->assertNotEmpty($snapshotMatch, 'No se encontró wire:snapshot en la página.');

        $sessionCookieName = config('session.cookie');
        $sessionCookieValue = null;
        foreach ($page->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionCookieName) {
                $sessionCookieValue = $cookie->getValue();
            }
        }
        $this->assertNotNull($sessionCookieValue, 'No se encontró la cookie de sesión en la respuesta.');

        $response = $this
            ->withCookie($sessionCookieName, $sessionCookieValue)
            ->withHeaders(['X-Livewire' => 'true', 'X-CSRF-TOKEN' => $csrfMatch[1], 'Accept' => 'application/json'])
            ->postJson('/livewire/update', [
                'components' => [[
                    'snapshot' => html_entity_decode($snapshotMatch[1]),
                    'updates' => ['turno' => 'vespertino', 'tipoAlumnado' => 'masculino'],
                    'calls' => [['path' => '', 'method' => 'guardarDatos', 'params' => []]],
                ]],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('escuela_niveles', ['id' => $escuelaNivel->id, 'turno' => 'vespertino', 'tipo_alumnado' => 'masculino']);
    }

    /** Revisión M9 (patrón de Paso2ResponsableHttpRoundTripTest): el snapshot del dueño reenviado por otro solicitante da 403, sin escribir. */
    public function test_un_no_dueno_recibe_403_al_reenviar_el_snapshot_por_livewire_update(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->completarPaso2($escuela->id);
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        $html = $this->actingAs($solicitante->user)->get(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $escuelaNivel->id]))->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]*)"/', $html, $match);
        $this->assertNotEmpty($match, 'No se encontró wire:snapshot en la página.');

        $update = fn () => $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => html_entity_decode($match[1]),
                'updates' => ['turno' => 'vespertino', 'tipoAlumnado' => 'mixto'],
                'calls' => [['path' => '', 'method' => 'guardarDatos', 'params' => []]],
            ]],
        ]);

        $this->actingAs(Solicitante::factory()->create()->user);
        $update()->assertForbidden();
        $this->assertNull($escuelaNivel->fresh()->turno);

        // Control: el mismo envío del dueño sí pasa.
        $this->actingAs($solicitante->user);
        $update()->assertOk();
        $this->assertSame('vespertino', $escuelaNivel->fresh()->turno);
    }
}
