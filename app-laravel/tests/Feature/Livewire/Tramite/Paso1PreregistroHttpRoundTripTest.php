<?php

namespace Tests\Feature\Livewire\Tramite;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reproduces the 419 diagnosed in docs/reports/2026-09-07-fix-sesion-419.md
 * over a REAL HTTP round-trip to /livewire/update — the code path
 * `Livewire::test()` never exercises, which is why the rest of the suite
 * stayed green the whole time this bug was live. See that report for the
 * full manual reproduction this test automates.
 */
class Paso1PreregistroHttpRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_wire_model_blur_en_paso1_no_produce_un_419(): void
    {
        $page = $this->get('/tramite/preregistro');
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
                    'updates' => ['calle' => 'Av. Reforma 100'],
                    'calls' => [],
                ]],
            ]);

        $response->assertOk();
    }
}
