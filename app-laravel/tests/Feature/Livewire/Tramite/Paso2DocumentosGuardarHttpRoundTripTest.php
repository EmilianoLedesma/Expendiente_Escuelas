<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WS-2 item 4 — round-trip HTTP real a /livewire/update para una escritura
 * de Paso2Documentos (ADR-003). Una subida de archivo real es difícil de
 * simular por HTTP crudo (Livewire sube el PDF a un endpoint de "temporary
 * upload" propio antes del round-trip normal, con su propia firma y
 * middleware), así que en su lugar se ejercita el camino de error de
 * validación de guardarDocumentoSimple() sin adjuntar archivo: confirma que
 * la respuesta trae el errorBag de Livewire (422), no un 500, sobre el
 * transporte HTTP real — que es exactamente el código que
 * Livewire::test() nunca ejercita (docs/reports/2026-09-07-fix-sesion-419.md).
 */
class Paso2DocumentosGuardarHttpRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardar_documento_simple_sin_archivo_responde_error_de_validacion_no_500(): void
    {
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));

        $page = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));
        $page->assertOk();

        $html = $page->getContent();

        preg_match('/name="csrf-token" content="([^"]+)"/', $html, $csrfMatch);
        $this->assertNotEmpty($csrfMatch, 'No se encontró el meta csrf-token en la página.');
        $csrfToken = $csrfMatch[1];

        preg_match('/wire:snapshot="([^"]*)"/', $html, $snapshotMatch);
        $this->assertNotEmpty($snapshotMatch, 'No se encontró wire:snapshot en la página.');
        $snapshotJson = html_entity_decode($snapshotMatch[1]);

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
            ->withHeaders([
                'X-Livewire' => 'true',
                'X-CSRF-TOKEN' => $csrfToken,
                'Accept' => 'application/json',
            ])
            ->postJson('/livewire/update', [
                'components' => [[
                    'snapshot' => $snapshotJson,
                    'updates' => [],
                    'calls' => [['path' => '', 'method' => 'guardarDocumentoSimple', 'params' => ['ine']]],
                ]],
            ]);

        // Livewire responde 200 con un errorBag embebido, no un 500, cuando
        // la validación falla — igual que Livewire::test()->assertHasErrors().
        $response->assertOk();
        $response->assertJsonPath('components.0.snapshot', fn ($snapshot) => str_contains((string) $snapshot, 'archivos.ine'));
        $this->assertDatabaseCount('documentos_escuela', 0);
    }
}
