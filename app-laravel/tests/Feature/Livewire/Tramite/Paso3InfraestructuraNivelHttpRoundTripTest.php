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
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

/**
 * WS-2 item 4 — round-trip HTTP real a /livewire/update (ADR-003: `Livewire::test()`
 * no ejecuta este camino, que es exactamente donde vivió el bug de 419 de
 * 2026-09-07). Cubre item 1 de esta ronda (unificación de "¿trae dato?") a
 * través del transporte HTTP real, no solo del test suite de Livewire::test().
 */
class Paso3InfraestructuraNivelHttpRoundTripTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private Solicitante $solicitante;

    private Plantel $plantel;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new TiposEspaciosSeeder)->run();

        $this->solicitante = Solicitante::factory()->create();
        $this->plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => $this->solicitante->id]);
        $this->completarPaso2($this->escuela->id);
    }

    private function escuelaNivel(string $claveNivel = 'primaria'): EscuelaNivel
    {
        $nivel = NivelEducativo::where('clave', $claveNivel)->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');

        return $escuelaNivel;
    }

    private function idTipo(string $clave): int
    {
        return (int) DB::table('tipos_espacios')->where('clave', $clave)->value('id');
    }

    /** GET la página y devuelve [html, csrfToken, snapshotJson, sessionCookieValue]. */
    private function abrirPagina(EscuelaNivel $escuelaNivel): array
    {
        $page = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $escuelaNivel->id]));
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

        return [$html, $csrfMatch[1], html_entity_decode($snapshotMatch[1]), $sessionCookieValue];
    }

    /** @param  array<string, mixed>  $updates */
    private function llamarGuardar(string $csrfToken, string $snapshotJson, string $sessionCookieValue, array $updates): TestResponse
    {
        $sessionCookieName = config('session.cookie');

        return $this
            ->withCookie($sessionCookieName, $sessionCookieValue)
            ->withHeaders([
                'X-Livewire' => 'true',
                'X-CSRF-TOKEN' => $csrfToken,
                'Accept' => 'application/json',
            ])
            ->postJson('/livewire/update', [
                'components' => [[
                    'snapshot' => $snapshotJson,
                    'updates' => $updates,
                    'calls' => [['path' => '', 'method' => 'guardar', 'params' => []]],
                ]],
            ]);
    }

    public function test_guardar_por_http_real_persiste_un_espacio_con_solo_superficie(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        [, $csrfToken, $snapshotJson, $sessionCookieValue] = $this->abrirPagina($escuelaNivel);

        $response = $this->llamarGuardar($csrfToken, $snapshotJson, $sessionCookieValue, [
            "espacios.{$direccionId}.superficieM2" => '18.5',
            'numeroAulas' => 6,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $direccionId,
            'superficie_m2' => 18.5,
        ]);
    }

    public function test_guardar_por_http_real_no_escribe_un_espacio_con_solo_ventilacion_natural_false(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        [, $csrfToken, $snapshotJson, $sessionCookieValue] = $this->abrirPagina($escuelaNivel);

        $response = $this->llamarGuardar($csrfToken, $snapshotJson, $sessionCookieValue, [
            "espacios.{$direccionId}.ventilacionNatural" => false,
            'numeroAulas' => 6,
        ]);

        $response->assertOk();
        // guardar() really ran (not a silent validation failure or early return).
        $this->assertDatabaseHas('aulas_nivel', ['escuela_nivel_id' => $escuelaNivel->id]);
        $this->assertDatabaseMissing('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $direccionId,
        ]);
    }
}
