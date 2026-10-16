<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Application\Revision\AsignarTramite;
use App\Livewire\Sedeq\ColaRevisor;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class ColaRevisorTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private User $rev;

    private EscuelaNivel $mio;

    private EscuelaNivel $ajeno;

    protected function setUp(): void
    {
        parent::setUp();
        $sup = $this->supervisor();
        $this->rev = $this->revisor();
        $this->mio = $this->nivelEnRevision();
        $this->ajeno = $this->nivelEnRevision();
        $this->nivelEnRevision(); // sin asignar
        app(AsignarTramite::class)->ejecutar($sup->id, $this->mio->id, $this->rev->id);
        app(AsignarTramite::class)->ejecutar($sup->id, $this->ajeno->id, $this->revisor()->id);
        $this->actingAs($this->rev);
    }

    public function test_lista_solo_sus_asignaciones_con_el_conteo_contra_el_limite(): void
    {
        config(['sedeq.revision.limite_por_revisor' => 7]);

        $c = Livewire::test(ColaRevisor::class)->assertSee('1 de 7 asignados');

        $this->assertSame([$this->mio->id], array_map(fn ($f) => $f->escuelaNivelId, $c->viewData('filas')));
    }

    public function test_el_panel_muestra_el_primero_con_enlace_de_lectura_y_hallazgos_vacios(): void
    {
        Livewire::test(ColaRevisor::class)
            ->assertSet('seleccionado', $this->mio->id)
            ->assertSee(route('sedeq.tramite', ['escuela' => $this->mio->escuela_id]))
            ->assertDontSee(route('sedeq.tramite', ['escuela' => $this->ajeno->escuela_id]))
            ->assertSee('Hallazgos del motor');
    }

    public function test_seleccionar_uno_ajeno_se_ignora_y_avisa(): void
    {
        Livewire::test(ColaRevisor::class)
            ->call('seleccionar', $this->ajeno->id)
            ->assertSet('seleccionado', $this->mio->id)
            ->assertDispatched('toast', tipo: 'error')
            ->assertDontSee(route('sedeq.tramite', ['escuela' => $this->ajeno->escuela_id]));
    }

    public function test_seleccionado_esta_bloqueado(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(ColaRevisor::class)->set('seleccionado', $this->ajeno->id);
    }

    public function test_cola_vacia(): void
    {
        $this->actingAs($this->revisor());

        Livewire::test(ColaRevisor::class)
            ->assertSet('seleccionado', null)
            ->assertSee('0 de 5 asignados')
            ->assertSee('No tienes trámites asignados');
    }
}
