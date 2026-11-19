<?php

namespace App\Domain\Calendario;

use DateTimeImmutable;

/** Suma de días hábiles; solo excluye sábado y domingo (sin calendario de feriados), por eso los plazos son aproximados. */
final class DiasHabiles
{
    public static function sumar(DateTimeImmutable $desde, int $dias): DateTimeImmutable
    {
        $fecha = $desde;
        while ($dias > 0) {
            $fecha = $fecha->modify('+1 day');
            if ((int) $fecha->format('N') < 6) {
                $dias--;
            }
        }

        return $fecha;
    }
}
