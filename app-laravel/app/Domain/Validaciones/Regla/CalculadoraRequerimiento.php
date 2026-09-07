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
            'personal_por_espacio' => $this->porEspacio($valorNumerico, $magnitud),
            'personal_umbral' => $this->umbral($valorNumerico, $condicionMin, $magnitud),
            'personal_proporcional' => $this->proporcional($valorNumerico, $redondeo, $magnitud),
            default => throw new InvalidArgumentException("tipo_calculo desconocido: {$tipoCalculo}"),
        };
    }

    /**
     * personal_umbral no tiene sentido sin un umbral: `$magnitud >=
     * $condicionMin` con $condicionMin null coacciona null a 0 en PHP, lo
     * que haría que la regla aplique siempre en vez de fallar. Se exige
     * condicionMin explícito.
     */
    private function umbral(float $valorNumerico, ?float $condicionMin, float $magnitud): int
    {
        if ($condicionMin === null) {
            throw new InvalidArgumentException('personal_umbral requiere condicionMin; recibido null.');
        }

        return $magnitud >= $condicionMin ? (int) $valorNumerico : 0;
    }

    /**
     * magnitud (conteo de espacios existentes) y valorNumerico (personal
     * por espacio) son conceptualmente enteros — un producto fraccionario
     * indica una entrada mal formada aguas arriba. Se falla en vez de
     * truncar en silencio con (int), que enmascararía ese error.
     */
    private function porEspacio(float $valorNumerico, float $magnitud): int
    {
        $producto = $magnitud * $valorNumerico;

        if (fmod($producto, 1.0) !== 0.0) {
            throw new InvalidArgumentException(
                "personal_por_espacio produjo un resultado no entero ({$producto}); magnitud y valorNumerico deben ser enteros para este tipo_calculo."
            );
        }

        return (int) $producto;
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
