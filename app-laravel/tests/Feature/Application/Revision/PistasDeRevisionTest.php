<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\PistasDeRevision;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\SeccionCapacidad;
use App\Application\Validaciones\DTO\SeccionNivel;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Application\Validaciones\EjecutarValidacionFinal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class PistasDeRevisionTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private function fila(string $clave, string $estado = 'no_cumple', ?string $paso = null): FilaValidacion
    {
        return new FilaValidacion($clave, "Titulo $clave", $estado, "Mensaje $clave", [], ["Linea $clave"], $paso);
    }

    /** @param list<SeccionNivel> $niveles @param list<SeccionCapacidad> $capacidad @param list<FilaValidacion> $filas */
    private function motorDevuelve(array $filas, array $niveles, array $capacidad): void
    {
        $this->mock(EjecutarValidacionFinal::class)->shouldReceive('evaluar')
            ->andReturn(new ValidacionFinal(null, now(), false, $filas, $capacidad, $niveles));
    }

    private function claves(array $filas): array
    {
        return array_map(fn (FilaValidacion $f) => $f->clave, $filas);
    }

    public function test_mapea_documentales_y_capacidad_a_sus_pasos(): void
    {
        $nivel = $this->nivelEnRevision();
        $this->motorDevuelve(
            [$this->fila('esc1')],
            [new SeccionNivel($nivel->id, 'Primaria', [$this->fila('niv1')])],
            [new SeccionCapacidad($nivel->id, 'Primaria', [
                $this->fila('cap.mob', 'no_cumple', 'mobiliario'),
                $this->fila('cap.inm', 'advertencia', 'inmueble'),
                $this->fila('cap.sinpaso'),
            ])],
        );

        $pistas = app(PistasDeRevision::class)->ejecutar($this->supervisor()->id, $nivel->id);

        $this->assertEqualsCanonicalizing(['documentos', 'documentos_nivel', 'mobiliario', 'inmueble'], array_keys($pistas));
        $this->assertSame(['esc1'], $this->claves($pistas['documentos']));
        $this->assertSame(['niv1'], $this->claves($pistas['documentos_nivel']));
        $this->assertSame(['cap.mob'], $this->claves($pistas['mobiliario']));
        $this->assertSame(['cap.inm'], $this->claves($pistas['inmueble']));
    }

    public function test_ignora_los_resultados_de_otros_niveles(): void
    {
        $nivel = $this->nivelEnRevision();
        $otro = $nivel->id + 1000;
        $this->motorDevuelve(
            [],
            [new SeccionNivel($nivel->id, 'Primaria', [$this->fila('mio')]), new SeccionNivel($otro, 'Secundaria', [$this->fila('ajeno')])],
            [new SeccionCapacidad($otro, 'Secundaria', [$this->fila('cap.ajena', 'no_cumple', 'mobiliario')])],
        );

        $pistas = app(PistasDeRevision::class)->ejecutar($this->supervisor()->id, $nivel->id);

        $this->assertSame(['documentos_nivel'], array_keys($pistas));
        $this->assertSame(['mio'], $this->claves($pistas['documentos_nivel']));
    }

    public function test_un_fallo_del_motor_devuelve_sin_pistas_y_no_rompe(): void
    {
        $nivel = $this->nivelEnRevision();
        $this->mock(EjecutarValidacionFinal::class)->shouldReceive('evaluar')->andThrow(new \RuntimeException('motor roto'));

        $this->assertSame([], app(PistasDeRevision::class)->ejecutar($this->supervisor()->id, $nivel->id));
    }

    public function test_tramite_incompleto_devuelve_sin_pistas(): void
    {
        $nivel = $this->nivelEnRevision();
        $this->mock(EjecutarValidacionFinal::class)->shouldReceive('evaluar')
            ->andThrow(new PrecondicionIncumplida('validacion_final', 'Incompleto'));

        $this->assertSame([], app(PistasDeRevision::class)->ejecutar($this->supervisor()->id, $nivel->id));
    }

    public function test_las_pistas_no_escriben_evaluaciones(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
        $antes = DB::table('evaluaciones_validacion')->count();

        $pistas = app(PistasDeRevision::class)->ejecutar($this->supervisor()->id, $nivel->id);

        $this->assertSame($antes, DB::table('evaluaciones_validacion')->count());
        $this->assertNotSame([], $pistas);
        $this->assertCount(6, $pistas['documentos']);
    }

    public function test_revisor_sin_asignacion_recibe_authorization_exception(): void
    {
        $nivel = $this->nivelEnRevision();

        $this->expectException(AuthorizationException::class);

        app(PistasDeRevision::class)->ejecutar($this->revisor()->id, $nivel->id);
    }
}
