<?php

namespace App\Domain\Validaciones\Regla;

use InvalidArgumentException;

/**
 * Calcula el requerimiento numérico de una fila de `reglas_validacion` dada
 * su magnitud de entrada (alumnos, grados, espacios existentes, etc., según
 * el `ambito` de la regla). Clase pura: sin Eloquent, sin framework, sin DB.
 *
 * `redondeo` distingue el mismo `tipo_calculo` con semánticas opuestas —
 * ver docs/reports/2026-09-07-reglas-validacion-schema.md, auditoría de
 * redondeo: los asistentes de Inicial redondean hacia arriba (COMPENDIO
 * línea 422), la Educación Física de Primaria redondea hacia abajo.
 */
class CalculadoraRequerimiento
{
    public function calcular(
        string $tipoCalculo,
        float $valorNumerico,
        ?float $condicionMin,
        string $redondeo,
        float $magnitud,
    ): int|float {
        return match ($tipoCalculo) {
            'ratio_por_alumno', 'ratio_por_grado', 'factor' => $magnitud * $valorNumerico,
            'minimo_fijo', 'adicional_fijo' => $valorNumerico,
            'personal_obligatorio' => (int) $valorNumerico,
            'personal_por_espacio' => (int) ($magnitud * $valorNumerico),
            'personal_umbral' => $magnitud >= $condicionMin ? (int) $valorNumerico : 0,
            'personal_proporcional' => $this->proporcional($valorNumerico, $redondeo, $magnitud),
            default => throw new InvalidArgumentException("tipo_calculo desconocido: {$tipoCalculo}"),
        };
    }

    private function proporcional(float $valorNumerico, string $redondeo, float $magnitud): int
    {
        $cociente = $magnitud / $valorNumerico;

        return match ($redondeo) {
            'arriba' => (int) ceil($cociente),
            'abajo' => (int) floor($cociente),
            default => throw new InvalidArgumentException("personal_proporcional requiere redondeo 'arriba' o 'abajo', recibido: {$redondeo}"),
        };
    }
}
