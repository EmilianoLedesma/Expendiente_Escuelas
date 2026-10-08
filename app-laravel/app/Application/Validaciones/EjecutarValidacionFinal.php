<?php

namespace App\Application\Validaciones;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\ResumenTramite;
use App\Application\Tramite\TramiteEditable;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\SeccionCapacidad;
use App\Application\Validaciones\DTO\SeccionNivel;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Infrastructure\Pdf\ReporteValidacionPdf;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Last step of the wizard (owner decision, ADR-007): once every section is
 * captured, run the documental engine over everything uploaded — the
 * escuela/plantel documents, then each level's Paso 2.4 documents — keep the
 * result as a PDF plus a row, and say whether the trámite may be sent
 * (ValidacionFinal::bloqueantes(): any documental no_cumple, and since WS-7a any capacity no_cumple or missing
 * capture; alerts and "no verificable por el sistema" do not).
 *
 * Sending itself (state change to en_revision, edit lock) is WS-7 —
 * PENDIENTE-edicion-hasta-envio.md. It must re-run this, not trust an older
 * row: data can change after a validation.
 */
class EjecutarValidacionFinal
{
    public const ETAPA = 'tramite';

    /**
     * Version of the "ready to send" rule stored in resultados['regla_envio'].
     * 2 = capacity no_cumple and missing-capture no_evaluable block (WS-7a);
     * UltimaValidacionFinal re-runs rows stored under an older rule.
     */
    public const REGLA_ENVIO = 2;

    public function __construct(
        private readonly ResumenTramite $resumenTramite,
        private readonly EjecutarValidacionDocumental $validacionDocumental,
        private readonly PresentadorValidacion $presentador,
        private readonly ReporteValidacionPdf $pdf,
        private readonly EjecutarValidacionCapacidad $validacionCapacidad,
        private readonly TramiteEditable $tramiteEditable,
    ) {}

    /** WS-7a: only while the trámite is editable; a sent trámite keeps the evaluation it was sent with. */
    public function ejecutar(int $escuelaId): ValidacionFinal
    {
        return DB::transaction(function () use ($escuelaId) {
            $this->tramiteEditable->asegurarEscuela($escuelaId);

            return $this->guardar($escuelaId, $this->evaluar($escuelaId));
        });
    }

    /**
     * Reads and evaluates, writes nothing; evaluacionId stays null until guardar().
     *
     * @throws PrecondicionIncumplida if a section of the trámite is still missing.
     */
    public function evaluar(int $escuelaId): ValidacionFinal
    {
        $resumen = $this->resumenTramite->paraEscuela($escuelaId);

        if (! $resumen->completo) {
            throw new PrecondicionIncumplida(self::ETAPA, 'Completa todas las secciones del trámite antes de la validación final.');
        }

        $reporte = $this->validacionDocumental->ejecutar($escuelaId);
        $filas = $this->presentador->filas($reporte);
        $niveles = [];

        foreach (EscuelaNivel::with('nivelEducativo')->where('escuela_id', $escuelaId)->orderBy('id')->get() as $escuelaNivel) {
            $reporteNivel = $this->validacionDocumental->paraNivel($escuelaNivel->id);
            $niveles[] = new SeccionNivel($escuelaNivel->id, $escuelaNivel->nivelEducativo->nombre, $this->presentador->filas($reporteNivel));
        }

        $capacidad = $this->capacidad($escuelaId);

        return new ValidacionFinal(null, Carbon::now(), ValidacionFinal::bloqueantes($filas, $capacidad, $niveles) === [], $filas, $capacidad, $niveles);
    }

    /**
     * Stores an evaluar() result as a PDF plus an evaluaciones_validacion row. No guard
     * here on purpose: EnviarTramite stores the evaluation it sends after the niveles
     * are already en_revision; ejecutar() guards the "validar de nuevo" path.
     */
    public function guardar(int $escuelaId, ValidacionFinal $validacion): ValidacionFinal
    {
        $resumen = $this->resumenTramite->paraEscuela($escuelaId);

        $ruta = $this->pdf->guardar($escuelaId, [
            'numero' => $resumen->numero(),
            'nombre' => $resumen->nombre,
            'domicilio' => $resumen->domicilio,
        ], $validacion->listaParaEnvio, $validacion->filas, $validacion->capacidad, $validacion->niveles, $validacion->generadaEn);

        try {
            $evaluacion = EvaluacionValidacion::create([
                'escuela_id' => $escuelaId,
                'archivo_path' => $ruta,
                'lista_para_envio' => $validacion->listaParaEnvio,
                'resultados' => [
                    'documental' => array_map(fn (FilaValidacion $fila) => $fila->aArreglo(), $validacion->filas),
                    'capacidad' => array_map(fn (SeccionCapacidad $seccion) => $seccion->aArreglo(), $validacion->capacidad),
                    'niveles' => array_map(fn (SeccionNivel $seccion) => $seccion->aArreglo(), $validacion->niveles),
                    'regla_envio' => self::REGLA_ENVIO,
                ],
                'created_at' => $validacion->generadaEn,
            ]);
        } catch (Throwable $e) {
            rescue(fn () => $this->pdf->eliminar($ruta), report: true);

            throw $e;
        }

        return new ValidacionFinal($evaluacion->id, $validacion->generadaEn, $validacion->listaParaEnvio, $validacion->filas, $validacion->capacidad, $validacion->niveles);
    }

    /** @return list<SeccionCapacidad> */
    private function capacidad(int $escuelaId): array
    {
        return EscuelaNivel::with('nivelEducativo')->where('escuela_id', $escuelaId)->orderBy('id')->get()
            ->map(fn (EscuelaNivel $escuelaNivel) => new SeccionCapacidad(
                $escuelaNivel->id,
                $escuelaNivel->nivelEducativo->nombre,
                $this->presentador->filasCapacidad(
                    $this->validacionCapacidad->ejecutar($escuelaNivel->id),
                    $this->validacionCapacidad->conceptos($escuelaNivel->id),
                ),
            ))
            ->values()
            ->all();
    }
}
