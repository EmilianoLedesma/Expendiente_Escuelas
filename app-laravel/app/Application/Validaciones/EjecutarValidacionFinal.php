<?php

namespace App\Application\Validaciones;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\ResumenTramite;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Infrastructure\Pdf\ReporteValidacionPdf;
use App\Models\EvaluacionValidacion;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Last step of the wizard (owner decision, ADR-007): once every section is
 * captured, run the documental engine over everything uploaded, keep the
 * result as a PDF plus a row, and say whether the trámite may be sent. Any
 * no_cumple blocks sending; alerts and no_evaluable do not.
 *
 * Sending itself (state change to en_revision, edit lock) is WS-7 —
 * PENDIENTE-edicion-hasta-envio.md. It must re-run this, not trust an older
 * row: data can change after a validation.
 */
class EjecutarValidacionFinal
{
    public const ETAPA = 'tramite';

    public function __construct(
        private readonly ResumenTramite $resumenTramite,
        private readonly EjecutarValidacionDocumental $validacionDocumental,
        private readonly PresentadorValidacion $presentador,
        private readonly ReporteValidacionPdf $pdf,
    ) {}

    public function ejecutar(int $escuelaId): ValidacionFinal
    {
        $resumen = $this->resumenTramite->paraEscuela($escuelaId);

        if (! $resumen->completo) {
            throw new PrecondicionIncumplida(self::ETAPA, 'Completa todas las secciones del trámite antes de la validación final.');
        }

        $reporte = $this->validacionDocumental->ejecutar($escuelaId);
        $filas = $this->presentador->filas($reporte);
        $listaParaEnvio = ! $reporte->tieneNoCumplimientos();
        $generadaEn = Carbon::now();

        $ruta = $this->pdf->guardar($escuelaId, [
            'numero' => $resumen->numero(),
            'nombre' => $resumen->nombre,
            'domicilio' => $resumen->domicilio,
        ], $listaParaEnvio, $filas, $generadaEn);

        try {
            $evaluacion = EvaluacionValidacion::create([
                'escuela_id' => $escuelaId,
                'archivo_path' => $ruta,
                'lista_para_envio' => $listaParaEnvio,
                'resultados' => array_map(fn (FilaValidacion $fila) => $fila->aArreglo(), $filas),
                'created_at' => $generadaEn,
            ]);
        } catch (Throwable $e) {
            rescue(fn () => $this->pdf->eliminar($ruta), report: true);

            throw $e;
        }

        return new ValidacionFinal($evaluacion->id, $generadaEn, $listaParaEnvio, $filas);
    }
}
