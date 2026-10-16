<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Application\Revision\AsignarTramite;
use App\Application\Revision\RolesSedeq;
use App\Livewire\Sedeq\Revisores;
use App\Models\AsignacionRevision;
use App\Models\User;
use App\Notifications\InvitacionRevisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class RevisoresTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private User $sup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = $this->supervisor();
        $this->actingAs($this->sup);
    }

    public function test_ve_las_barras_con_llena_en_texto_y_el_formulario(): void
    {
        config(['sedeq.revision.limite_por_revisor' => 1]);
        $rev = $this->revisor();
        $rev->update(['name' => 'Ana Revisora']);
        app(AsignarTramite::class)->ejecutar($this->sup->id, $this->nivelEnRevision()->id, $rev->id);

        Livewire::test(Revisores::class)
            ->assertSee('Ana Revisora')
            ->assertSee('1 de 1 · llena')
            ->assertSee('Invitar revisor');
    }

    public function test_invitar_crea_el_revisor_y_pone_el_correo_en_cola(): void
    {
        Notification::fake();

        Livewire::test(Revisores::class)
            ->set('nombre', 'Sofía Torres')
            ->set('correo', 'sofia@sedeq.test')
            ->call('invitar')
            ->assertReturned(true)
            ->assertHasNoErrors()
            ->assertSet('correo', '')
            ->assertSee('Sofía Torres');

        $u = User::where('email', 'sofia@sedeq.test')->firstOrFail();
        $this->assertTrue($u->hasRole(RolesSedeq::REVISOR));
        Notification::assertSentTo($u, InvitacionRevisor::class);
    }

    public function test_invitar_con_datos_invalidos_marca_los_campos_y_limpia_errores_viejos(): void
    {
        Notification::fake();

        Livewire::test(Revisores::class)
            ->set('nombre', '')
            ->set('correo', 'no-es-correo')
            ->call('invitar')
            ->assertReturned(false)
            ->assertHasErrors(['nombre', 'correo'])
            ->set('nombre', 'Sofía Torres')
            ->call('invitar')
            ->assertHasErrors('correo')
            ->assertHasNoErrors('nombre');
    }

    public function test_el_modal_de_revocar_nombra_al_revisor_y_explica_que_pasa(): void
    {
        $rev = $this->revisor();
        $rev->update(['name' => 'Raul Revisor']);

        Livewire::test(Revisores::class)
            ->assertDontSee('wire:confirm', false)
            ->assertSeeHtml('<dialog id="revocar-'.$rev->id.'"')
            ->assertSee('¿Revocar a Raul Revisor?')
            ->assertSee('regresan a «Sin asignar»')
            ->assertSee('Revocar acceso')
            ->assertSee('Cancelar');
    }

    public function test_revocar_quita_el_rol_y_los_tramites_regresan_a_sin_asignar(): void
    {
        $rev = $this->revisor();
        $nivel = $this->nivelEnRevision();
        app(AsignarTramite::class)->ejecutar($this->sup->id, $nivel->id, $rev->id);

        Livewire::test(Revisores::class)
            ->call('revocar', $rev->id)
            ->assertDispatched('toast');

        $this->assertFalse($rev->fresh()->hasRole(RolesSedeq::REVISOR));
        $this->assertFalse(AsignacionRevision::where('escuela_nivel_id', $nivel->id)->exists());
    }

    public function test_cada_revisor_se_expande_con_sus_tramites_en_orden_de_prioridad(): void
    {
        $rev = $this->revisor();
        $vacio = $this->revisor();
        $media = $this->nivelEnRevision();
        $alta = $this->nivelEnRevision();
        foreach ([$media, $alta] as $n) {
            app(AsignarTramite::class)->ejecutar($this->sup->id, $n->id, $rev->id);
        }
        DB::table('prioridades_revision')->insert(['escuela_nivel_id' => $alta->id, 'prioridad' => 'alta']);

        $html = Livewire::test(Revisores::class)
            ->assertSeeHtml('aria-expanded="false"')
            ->assertSeeHtml('aria-controls="tramites-'.$rev->id.'"')
            ->assertSee('Sin trámites asignados.')
            ->assertSeeHtmlInOrder([
                route('sedeq.tramite', ['escuela' => $alta->escuela_id]),
                route('sedeq.tramite', ['escuela' => $media->escuela_id]),
            ])
            ->assertSee('Prioridad Alta')
            ->html();

        $this->assertSame(2, substr_count($html, 'aria-expanded="false"'));
        $this->assertStringContainsString('id="tramites-'.$vacio->id.'"', $html);
    }

    public function test_un_revisor_recibe_403_al_montar(): void
    {
        $this->actingAs($this->revisor());

        Livewire::test(Revisores::class)->assertForbidden();
    }
}
