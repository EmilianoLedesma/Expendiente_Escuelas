<?php

namespace App\Domain\Validaciones\Resultado;

/**
 * Outcome of one validation rule. Deliberately four states, not PASS/FAIL:
 * NoEvaluable means the system lacked the data to decide (e.g. a fact was
 * never captured) and must never be shown to the applicant as their
 * failure. Whether any state blocks the flow is decided by the calling
 * Application use case, not here — see
 * docs/reports/2026-09-30-evaluacion-motor-validacion.md §7.
 */
enum EstadoResultado: string
{
    case Cumple = 'cumple';
    case Advertencia = 'advertencia';
    case NoCumple = 'no_cumple';
    case NoEvaluable = 'no_evaluable';
}
