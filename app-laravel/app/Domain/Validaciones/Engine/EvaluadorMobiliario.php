<?php

namespace App\Domain\Validaciones\Engine;

use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ResultadoRegla;

/**
 * Inicial furniture (COMPENDIO §5, "Tercera dimensión"): for every sala with
 * enrolled children, each concept of that sala must be declared in at least
 * the required quantity. "1 por cada N niños" rounds up — the norm does not
 * say, rounding down would leave children without the item.
 *
 * Concepts without a sala (Sala de Usos Múltiples) are not evaluated: the
 * catalog does not say whose children their ratio counts (lactantes,
 * maternales or all). They are listed in `no_evaluados`.
 */
final class EvaluadorMobiliario
{
    public const CLAVE = 'inicial.mobiliario';

    /**
     * @param  array<string, int>|null  $matriculaPorSala
     * @param  list<LineaMobiliario>  $lineas
     */
    public function evaluar(?array $matriculaPorSala, array $lineas): ResultadoRegla
    {
        if ($matriculaPorSala === null) {
            return new ResultadoRegla(self::CLAVE, EstadoResultado::NoEvaluable, 'Mobiliario: falta capturar la matrícula por sala.');
        }

        $faltantes = [];
        $noEvaluados = [];

        foreach ($lineas as $linea) {
            if ($linea->salaClave === null) {
                $noEvaluados[] = $linea->concepto;

                continue;
            }

            $alumnos = $matriculaPorSala[$linea->salaClave] ?? 0;
            if ($alumnos <= 0) {
                continue;
            }

            $requerido = $linea->tipoRatio === 'fijo_por_sala'
                ? (int) $linea->valorRatio
                : (int) ceil($alumnos / $linea->valorRatio);
            $declarado = $linea->declarada ?? 0;

            if ($declarado < $requerido) {
                $faltantes[] = ['concepto' => $linea->concepto, 'sala' => $linea->salaClave, 'requerido' => $requerido, 'declarado' => $declarado];
            }
        }

        $detalles = ['faltantes' => $faltantes, 'no_evaluados' => $noEvaluados];

        return $faltantes === []
            ? new ResultadoRegla(self::CLAVE, EstadoResultado::Cumple, 'Mobiliario: cada sala cuenta con lo requerido.', $detalles)
            : new ResultadoRegla(self::CLAVE, EstadoResultado::NoCumple, 'Mobiliario: falta mobiliario en '.count($faltantes).' concepto(s).', $detalles);
    }
}
