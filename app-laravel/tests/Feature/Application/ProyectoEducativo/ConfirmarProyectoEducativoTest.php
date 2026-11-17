<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\ConfirmarProyectoEducativo;
use App\Application\ProyectoEducativo\EstadoSeccionesProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class ConfirmarProyectoEducativoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    /** Las 20 secciones con el estado pedido; $pendiente deja una sin completar. */
    private function simularSecciones(?string $pendiente = null): void
    {
        $estado = collect(SeccionesProyectoEducativo::todas())->map(fn ($_, string $slug) => $slug !== $pendiente)->all();
        $this->mock(EstadoSeccionesProyecto::class, fn ($m) => $m->shouldReceive('completas')->andReturn($estado));
    }

    private function completado(int $nivelId): bool
    {
        return app(EstadoPaso3::class)->completado($nivelId, SeccionesProyectoEducativo::CLAVE_PASO);
    }

    public function test_con_las_veinte_completas_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->simularSecciones();

        app(ConfirmarProyectoEducativo::class)->ejecutar($nivel->id);

        $this->assertTrue($this->completado($nivel->id));
    }

    public function test_confirmar_dos_veces_es_idempotente(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->simularSecciones();

        app(ConfirmarProyectoEducativo::class)->ejecutar($nivel->id);
        app(ConfirmarProyectoEducativo::class)->ejecutar($nivel->id);

        $this->assertTrue($this->completado($nivel->id));
    }

    public function test_con_una_seccion_incompleta_se_rechaza_y_no_marca(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->simularSecciones(SeccionesProyectoEducativo::bibliografia());

        try {
            app(ConfirmarProyectoEducativo::class)->ejecutar($nivel->id);
            $this->fail('Debió rechazar la confirmación.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(SeccionesProyectoEducativo::CLAVE_PASO, $e->etapaFaltante);
        }
        $this->assertFalse($this->completado($nivel->id));
    }

    public function test_sin_ninguna_seccion_guardada_se_rechaza(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->expectException(PrecondicionIncumplida::class);
        app(ConfirmarProyectoEducativo::class)->ejecutar($nivel->id);
    }

    public function test_tramite_enviado_rechaza_la_confirmacion(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->simularSecciones();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            app(ConfirmarProyectoEducativo::class)->ejecutar($nivel->id);
            $this->fail('Debió rechazar la confirmación.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertFalse($this->completado($nivel->id));
    }

    public function test_antes_de_matricula_rechaza_la_confirmacion(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');
        $this->simularSecciones();

        $this->expectException(PrecondicionIncumplida::class);
        app(ConfirmarProyectoEducativo::class)->ejecutar($nivel->id);
    }

    public function test_fuera_de_media_superior_no_existe_el_paso(): void
    {
        $nivel = $this->nivelListoPara('primaria', 'matricula');
        $this->simularSecciones();

        $this->expectException(InvalidArgumentException::class);
        app(ConfirmarProyectoEducativo::class)->ejecutar($nivel->id);
    }
}
