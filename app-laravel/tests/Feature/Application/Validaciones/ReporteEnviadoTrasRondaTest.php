<?php

namespace Tests\Feature\Application\Validaciones;

use App\Application\Revision\ConsultarTramiteSedeq;
use App\Application\Tramite\EnviarTramite;
use App\Application\Tramite\ResumenTramite;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Application\Validaciones\ReporteValidacionGuardado;
use App\Application\Validaciones\UltimaValidacionFinal;
use App\Models\EvaluacionValidacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/**
 * El cierre de una ronda guarda la línea base del G6 como una evaluación más: el «reporte enviado» sigue siendo la
 * evaluación guardada al envío, y el G6 (UltimaValidacionFinal::paraEscuela) sigue leyendo la última.
 */
class ReporteEnviadoTrasRondaTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramiteDevuelto;
    use RefreshDatabase;

    public function test_tras_cerrar_una_ronda_el_reporte_enviado_sigue_siendo_el_del_envio(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $enviada = app(EnviarTramite::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);

        // Cierre de ronda (DecidirPaso::cerrarRonda): evalúa sin exigir completo y guarda la línea base.
        $this->travel(2)->hours();
        $final = app(EjecutarValidacionFinal::class);
        $base = $final->guardar($escuela->id, $final->evaluar($escuela->id, exigirCompleto: false));
        $this->assertNotSame($enviada->evaluacionId, $base->evaluacionId);

        $rutaEnviada = EvaluacionValidacion::whereKey($enviada->evaluacionId)->value('archivo_path');
        $this->assertSame($rutaEnviada, app(ReporteValidacionGuardado::class)->rutaEnviada($escuela->id));
        $this->assertSame($enviada->evaluacionId, app(ResumenTramite::class)->paraEscuela($escuela->id)->reporteEnviadoId);
        $sedeq = app(ConsultarTramiteSedeq::class)->ejecutar($this->supervisor()->id, $escuela->id);
        $this->assertSame($enviada->generadaEn->format('Y-m-d H:i:s'), $sedeq['validacion']['generadaEn']->format('Y-m-d H:i:s'));

        $this->assertSame($base->evaluacionId, app(UltimaValidacionFinal::class)->paraEscuela($escuela->id)?->evaluacionId);
    }
}
