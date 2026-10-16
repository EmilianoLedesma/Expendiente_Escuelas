<?php

namespace Tests\Feature\View;

use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Walk-through #1/#1a/#2/#9: marca y menú de los roles SEDEQ. */
class ShellSedeqTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private const NAV = 'aria-label="Mi cuenta"';

    public function test_supervisor_tablero_sin_sidebar_y_con_marca_de_revision(): void
    {
        $this->actingAs($this->supervisor())->get('/sedeq')
            ->assertOk()
            ->assertDontSee(self::NAV, false)
            ->assertSee('Revisión de incorporación')
            ->assertDontSee('Supervisor')
            ->assertDontSeeHtml('align-middle text-caption font-semibold uppercase tracking-wide text-primary')
            ->assertDontSee('Trámite de Incorporación de Escuelas Particulares')
            ->assertDontSee('Mis trámites');
    }

    public function test_revisor_mis_revisiones_sin_sidebar_y_con_marca_de_revision(): void
    {
        $this->actingAs($this->revisor())->get('/sedeq/cola')
            ->assertOk()
            ->assertDontSee(self::NAV, false)
            ->assertSee('Revisión de incorporación')
            ->assertSee('Mis Revisiones')
            ->assertDontSee('Revisor')
            ->assertDontSee('Mi cola')
            ->assertDontSee('Trámite de Incorporación de Escuelas Particulares')
            ->assertDontSee('Mis trámites');
    }

    public function test_perfil_lleva_sidebar_por_rol(): void
    {
        $this->actingAs($this->supervisor())->get('/profile')
            ->assertOk()->assertSee(self::NAV, false)->assertDontSee('Mi cola');
        $this->actingAs($this->revisor())->get('/profile')
            ->assertOk()->assertSee(self::NAV, false)->assertSee('Mis Revisiones')->assertDontSee('Mi cola');
    }

    public function test_solicitante_conserva_el_nombre_original(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user)->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('Trámite de Incorporación de Escuelas Particulares')
            ->assertDontSee('Revisión de incorporación');
    }
}
