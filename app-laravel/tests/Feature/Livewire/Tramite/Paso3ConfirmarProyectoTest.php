<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\EstadoSeccionesProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Livewire\Tramite\Paso3\ProyectoEducativoIndice;
use App\Models\EscuelaNivel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3ConfirmarProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const BOTON = 'Confirmar proyecto educativo';

    private function nivel(): EscuelaNivel
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->actingAs($nivel->escuela->solicitante->user);

        return $nivel;
    }

    private function simularSecciones(?string $pendiente = null): void
    {
        $estado = collect(SeccionesProyectoEducativo::todas())->map(fn ($_, string $slug) => $slug !== $pendiente)->all();
        $this->mock(EstadoSeccionesProyecto::class, fn ($m) => $m->shouldReceive('completas')->andReturn($estado));
    }

    private function componente(EscuelaNivel $nivel)
    {
        return Livewire::test(ProyectoEducativoIndice::class, ['escuelaNivel' => $nivel]);
    }

    private function completado(EscuelaNivel $nivel): bool
    {
        return app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO);
    }

    public function test_sin_las_veinte_completas_no_hay_boton(): void
    {
        $this->componente($this->nivel())->assertDontSee(self::BOTON);
    }

    public function test_con_una_incompleta_no_hay_boton(): void
    {
        $nivel = $this->nivel();
        $this->simularSecciones(SeccionesProyectoEducativo::bibliografia());

        $this->componente($nivel)->assertDontSee(self::BOTON);
    }

    public function test_con_las_veinte_completas_aparece_el_boton(): void
    {
        $nivel = $this->nivel();
        $this->simularSecciones();

        $this->componente($nivel)->assertSee(self::BOTON);
    }

    public function test_confirmar_marca_el_paso_avisa_y_oculta_el_boton(): void
    {
        $nivel = $this->nivel();
        $this->simularSecciones();

        $this->componente($nivel)->call('confirmar')
            ->assertDispatched('toast', tipo: 'success', titulo: 'Proyecto educativo confirmado')
            ->assertDontSee(self::BOTON)
            ->assertSee('Confirmado');

        $this->assertTrue($this->completado($nivel));
    }

    public function test_confirmar_con_una_incompleta_avisa_y_no_marca(): void
    {
        $nivel = $this->nivel();
        $this->simularSecciones(SeccionesProyectoEducativo::bibliografia());

        $this->componente($nivel)->call('confirmar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se pudo confirmar')
            ->assertNotDispatched('toast', titulo: 'Proyecto educativo confirmado');

        $this->assertFalse($this->completado($nivel));
    }

    public function test_tramite_enviado_no_se_confirma(): void
    {
        $nivel = $this->nivel();
        $this->simularSecciones();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->componente($nivel)->assertDontSee(self::BOTON)->call('confirmar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se pudo confirmar');

        $this->assertFalse($this->completado($nivel));
    }
}
