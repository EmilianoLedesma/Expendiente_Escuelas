<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Application\Revision\AsignarTramite;
use App\Livewire\Sedeq\TableroSupervisor;
use App\Models\AsignacionRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class TableroSupervisorTest extends TestCase
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

    public function test_ve_la_tabla_de_tramites_y_el_resumen_de_carga_sin_invitar_ni_revocar(): void
    {
        $asignado = $this->nivelEnRevision();
        $libre = $this->nivelEnRevision();
        $this->nivelEnRevision('en_captura');
        $rev = $this->revisor();
        $rev->update(['name' => 'Ana Revisora']);
        app(AsignarTramite::class)->ejecutar($this->sup->id, $asignado->id, $rev->id);

        $c = Livewire::test(TableroSupervisor::class)
            ->assertSee('Ana Revisora')
            ->assertSee('Ana Revisora 1/5')
            ->assertSee('Sin asignar')
            ->assertDontSee('Invitar revisor')
            ->assertDontSee('Revocar')
            ->assertSeeHtml('pr-10')
            ->assertSee(route('sedeq.tramite', ['escuela' => $libre->escuela_id]));

        $this->assertSame([$asignado->id, $libre->id], array_map(fn ($f) => $f->escuelaNivelId, $c->viewData('filas')));
    }

    public function test_la_fila_alterna_su_casilla_con_clic_salvo_sobre_controles_interactivos(): void
    {
        $this->nivelEnRevision();

        Livewire::test(TableroSupervisor::class)
            ->assertSeeHtml('x-ref="chk"')
            ->assertSeeHtml('$refs.chk.click()')
            ->assertSeeHtml("closest('a, button, select, input, label')")
            ->assertSeeHtml('cursor-pointer')
            ->assertSeeHtml('hover:bg-surface-soft')
            ->assertSeeHtml('has-[input[type=checkbox]:checked]:bg-surface-soft')
            ->assertDontSeeHtml('has-[:checked]')
            ->assertDontSeeHtml('<tbody class="divide-y divide-hairline bg-')
            ->assertSeeHtml('wire:model="seleccion"')
            ->assertDontSeeHtml('tabindex');
    }

    public function test_asignar_seleccion_asigna_y_limpia_la_seleccion(): void
    {
        $a = $this->nivelEnRevision();
        $b = $this->nivelEnRevision();
        $rev = $this->revisor();

        Livewire::test(TableroSupervisor::class)
            ->set('seleccion', [(string) $a->id, (string) $b->id])
            ->call('asignarSeleccion', [$a->id, $b->id], $rev->id)
            ->assertHasNoErrors()
            ->assertSet('seleccion', [])
            ->assertDispatched('toast');

        $this->assertSame(2, AsignacionRevision::where('revisor_id', $rev->id)->count());
    }

    public function test_sin_seleccion_o_sin_revisor_marca_el_error_y_no_asigna(): void
    {
        $a = $this->nivelEnRevision();
        $rev = $this->revisor();

        Livewire::test(TableroSupervisor::class)
            ->call('asignarSeleccion', [], $rev->id)
            ->assertHasErrors('seleccion')
            ->call('asignarSeleccion', [$a->id], 0)
            ->assertHasErrors('revisorDestino');

        $this->assertSame(0, AsignacionRevision::count());
    }

    public function test_el_sexto_muestra_el_aviso_sin_cambiar_nada_y_confirmar_desasigna_el_esperado(): void
    {
        $rev = $this->revisor();
        $rev->update(['name' => 'Luis Revisor']);
        $niveles = collect(range(1, 5))->map(fn () => $this->nivelEnRevision());
        foreach ($niveles as $i => $n) {
            app(AsignarTramite::class)->ejecutar($this->sup->id, $n->id, $rev->id);
            DB::table('asignaciones_revision')->where('escuela_nivel_id', $n->id)->update(['asignado_at' => now()->subDays(10 - $i)]);
        }
        $sexto = $this->nivelEnRevision();

        $c = Livewire::test(TableroSupervisor::class)
            ->call('asignarSeleccion', [$sexto->id], $rev->id)
            ->assertDispatched('toast', tipo: 'info', titulo: 'Luis Revisor llegó a su límite de 5 trámites')
            ->assertSeeHtml('<dialog id="sobrecarga"')
            ->assertSee('Luis Revisor ya tiene 5 trámites asignados')
            ->assertSee('Asignar de todos modos')
            ->assertSee('Cancelar');
        $this->assertSame(5, AsignacionRevision::where('revisor_id', $rev->id)->count());
        $this->assertFalse(AsignacionRevision::whereKey($sexto->id)->exists());

        $c->call('asignarSeleccion', [$sexto->id], $rev->id, true)
            ->assertDontSee('Asignar de todos modos')
            ->assertDontSeeHtml('<dialog id="sobrecarga"')
            ->assertSet('sobrecarga', []);

        $this->assertTrue(AsignacionRevision::where('escuela_nivel_id', $sexto->id)->where('revisor_id', $rev->id)->exists());
        // Todos con progreso 0 y prioridad media: sale el asignado más recientemente.
        $this->assertFalse(AsignacionRevision::where('escuela_nivel_id', $niveles[4]->id)->exists());
        $this->assertSame(5, AsignacionRevision::where('revisor_id', $rev->id)->count());
    }

    public function test_cancelar_la_sobrecarga_quita_el_aviso_y_el_estado_esta_bloqueado(): void
    {
        $rev = $this->revisor();
        config(['sedeq.revision.limite_por_revisor' => 1]);
        app(AsignarTramite::class)->ejecutar($this->sup->id, $this->nivelEnRevision()->id, $rev->id);
        $otro = $this->nivelEnRevision();

        $c = Livewire::test(TableroSupervisor::class)
            ->call('asignarSeleccion', [$otro->id], $rev->id)
            ->assertSee('Asignar de todos modos')
            ->call('cancelarSobrecarga')
            ->assertDontSee('Asignar de todos modos');

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $c->set('sobrecarga', ['ids' => [1], 'revisorId' => 1, 'limite' => 1]);
    }

    public function test_un_error_de_campo_se_limpia_al_asignar_bien_despues(): void
    {
        $a = $this->nivelEnRevision();
        $rev = $this->revisor();

        Livewire::test(TableroSupervisor::class)
            ->call('asignarSeleccion', [], $rev->id)
            ->assertHasErrors('seleccion')
            ->call('asignarSeleccion', [$a->id], 0)
            ->assertHasErrors('revisorDestino')
            ->assertHasNoErrors('seleccion')
            ->call('asignarSeleccion', [$a->id], $rev->id)
            ->assertHasNoErrors();
    }

    public function test_una_seleccion_mayor_que_el_tope_avisa_sin_ofrecer_confirmar(): void
    {
        config(['sedeq.revision.limite_por_revisor' => 1]);
        $rev = $this->revisor();
        $ids = [$this->nivelEnRevision()->id, $this->nivelEnRevision()->id];

        Livewire::test(TableroSupervisor::class)
            ->call('asignarSeleccion', $ids, $rev->id)
            ->assertSet('sobrecarga', [])
            ->assertHasErrors('seleccion')
            ->assertSee('Seleccionaste 2; el tope por revisor es 1.')
            ->assertDontSee('Asignar de todos modos');

        $this->assertSame(0, AsignacionRevision::count());
    }

    public function test_si_confirmar_falla_por_otra_causa_se_quita_el_aviso(): void
    {
        config(['sedeq.revision.limite_por_revisor' => 1]);
        $rev = $this->revisor();
        app(AsignarTramite::class)->ejecutar($this->sup->id, $this->nivelEnRevision()->id, $rev->id);
        $otro = $this->nivelEnRevision();

        $c = Livewire::test(TableroSupervisor::class)
            ->call('asignarSeleccion', [$otro->id], $rev->id)
            ->assertSee('Asignar de todos modos');

        $otro->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id')]);

        $c->call('asignarSeleccion', [$otro->id], $rev->id, true)
            ->assertSet('sobrecarga', [])
            ->assertDispatched('toast', tipo: 'error')
            ->assertDontSee('Asignar de todos modos');
    }

    public function test_un_revisor_recibe_403_al_montar(): void
    {
        $this->actingAs($this->revisor());

        Livewire::test(TableroSupervisor::class)->assertForbidden();
    }

    public function test_cambiar_prioridad_devuelve_el_foco_a_su_select(): void
    {
        $nivel = $this->nivelEnRevision();

        Livewire::test(TableroSupervisor::class)
            ->call('cambiarPrioridad', $nivel->id, 'alta')
            ->assertDispatched('prioridad-cambiada', id: $nivel->id);
    }

    public function test_asignar_a_alguien_que_no_es_revisor_avisa_sin_403(): void
    {
        $a = $this->nivelEnRevision();

        Livewire::test(TableroSupervisor::class)
            ->call('asignarSeleccion', [$a->id], User::factory()->create()->id)
            ->assertOk()
            ->assertDispatched('toast', tipo: 'error');

        $this->assertSame(0, AsignacionRevision::count());
    }

    public function test_cambiar_prioridad_reordena(): void
    {
        $primero = $this->nivelEnRevision();
        $segundo = $this->nivelEnRevision();

        $c = Livewire::test(TableroSupervisor::class);
        $this->assertSame($primero->id, $c->viewData('filas')[0]->escuelaNivelId);

        $c->call('cambiarPrioridad', $segundo->id, 'alta')->assertHasNoErrors();
        $this->assertSame([$segundo->id, $primero->id], array_map(fn ($f) => $f->escuelaNivelId, $c->viewData('filas')));

        $c->call('cambiarPrioridad', $segundo->id, 'urgente')->assertDispatched('toast', tipo: 'error');
    }
}
