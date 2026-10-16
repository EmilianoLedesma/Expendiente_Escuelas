<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Application\Revision\AsignarTramite;
use App\Application\Revision\RolesSedeq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Livewire::test() salta el middleware de ruta y el persistente: aquí va el GET real y luego POST /livewire/update. */
class HudSedeqHttpRoundTripTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private function snapshot(TestResponse $pagina): string
    {
        preg_match('/wire:snapshot="([^"]*)"/', $pagina->assertOk()->getContent(), $m);
        $this->assertNotEmpty($m, 'No se encontró wire:snapshot en la página.');

        return html_entity_decode($m[1]);
    }

    private function llamar(string $snapshot, string $metodo, array $params): TestResponse
    {
        return $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [['path' => '', 'method' => $metodo, 'params' => $params]],
            ]],
        ]);
    }

    public function test_cada_rol_solo_abre_su_pantalla(): void
    {
        $sup = $this->supervisor();
        $rev = $this->revisor();
        $sedeq = User::factory()->create()->assignRole('sedeq');

        $this->actingAs($rev)->get('/sedeq')->assertForbidden();
        $this->actingAs($sup)->get('/sedeq/cola')->assertForbidden();
        $this->actingAs($sedeq)->get('/sedeq')->assertForbidden();
        $this->actingAs($sedeq)->get('/sedeq/cola')->assertForbidden();
        $this->actingAs($sup)->get('/admin')->assertForbidden();
        $this->actingAs($sup)->get('/sedeq')->assertOk()->assertSeeLivewire('sedeq.tablero-supervisor');
        $this->actingAs($rev)->get('/sedeq/cola')->assertOk()->assertSeeLivewire('sedeq.cola-revisor');
    }

    public function test_solo_el_supervisor_abre_revisores_y_la_pagina_lleva_el_menu_lateral(): void
    {
        $rev = $this->revisor();
        $sedeq = User::factory()->create()->assignRole('sedeq');

        $this->actingAs($rev)->get('/sedeq/revisores')->assertForbidden();
        $this->actingAs($sedeq)->get('/sedeq/revisores')->assertForbidden();
        auth()->logout();
        $this->get('/sedeq/revisores')->assertRedirect(route('login'));

        $this->actingAs($this->supervisor())->get('/sedeq/revisores')
            ->assertOk()
            ->assertSeeLivewire('sedeq.revisores')
            ->assertSee('aria-label="Mi cuenta"', false)
            ->assertSee(route('sedeq.revisores'), false);
    }

    public function test_el_supervisor_revoca_por_la_ruta_real_y_un_revisor_con_el_snapshot_recibe_403(): void
    {
        $sup = $this->supervisor();
        $rev = $this->revisor();

        $snapshot = $this->snapshot($this->actingAs($sup)->get('/sedeq/revisores'));

        $this->actingAs($this->revisor());
        $this->llamar($snapshot, 'revocar', [$rev->id])->assertForbidden();
        $this->assertTrue($rev->fresh()->hasRole(RolesSedeq::REVISOR));

        $this->actingAs($sup);
        $this->llamar($snapshot, 'revocar', [$rev->id])->assertOk();
        $this->assertFalse($rev->fresh()->hasRole(RolesSedeq::REVISOR));
    }

    public function test_sin_sesion_o_sin_verificar_redirige(): void
    {
        $this->get('/sedeq')->assertRedirect(route('login'));
        $this->get('/sedeq/cola')->assertRedirect(route('login'));

        $sinVerificar = User::factory()->unverified()->create();
        $this->supervisor();
        $sinVerificar->assignRole(RolesSedeq::SUPERVISOR);
        $this->actingAs($sinVerificar)->get('/sedeq')->assertRedirect(route('verification.notice'));

        $revisorSinVerificar = User::factory()->unverified()->create()->assignRole(RolesSedeq::REVISOR);
        $this->actingAs($revisorSinVerificar)->get('/sedeq/cola')->assertRedirect(route('verification.notice'));
    }

    public function test_revisor_sin_rol_tras_cargar_la_pagina_recibe_403_al_seleccionar(): void
    {
        $sup = $this->supervisor();
        $rev = $this->revisor();
        $nivel = $this->nivelEnRevision();
        app(AsignarTramite::class)->ejecutar($sup->id, $nivel->id, $rev->id);

        $snapshot = $this->snapshot($this->actingAs($rev)->get('/sedeq/cola'));
        $this->llamar($snapshot, 'seleccionar', [$nivel->id])->assertOk();

        $rev->removeRole(RolesSedeq::REVISOR);
        $this->llamar($snapshot, 'seleccionar', [$nivel->id])->assertForbidden();
    }

    public function test_un_revisor_que_reenvia_el_snapshot_del_tablero_recibe_403(): void
    {
        $sup = $this->supervisor();
        $nivel = $this->nivelEnRevision();

        $snapshot = $this->snapshot($this->actingAs($sup)->get('/sedeq'));

        $this->actingAs($this->revisor());
        $this->llamar($snapshot, 'cambiarPrioridad', [$nivel->id, 'alta'])->assertForbidden();

        $this->actingAs($sup);
        $this->llamar($snapshot, 'cambiarPrioridad', [$nivel->id, 'alta'])->assertOk();
    }
}
