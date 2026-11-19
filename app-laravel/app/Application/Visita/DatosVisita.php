<?php

namespace App\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use Carbon\Carbon;

/** Validación compartida de fecha e inspector de una visita (ProgramarVisita y ActualizarVisita). */
final class DatosVisita
{
    /**
     * @return array{0: Carbon, 1: string} fecha e inspector ya recortados
     *
     * @throws DatosInvalidos
     */
    public static function validar(\DateTimeInterface $fecha, string $inspector): array
    {
        $inspector = trim($inspector);
        $fecha = Carbon::instance($fecha);
        $errores = [];

        if ($fecha->toDateString() < today()->toDateString()) {
            $errores['fecha'] = 'La fecha de la visita no puede ser anterior a hoy.';
        }
        if ($inspector === '' || mb_strlen($inspector) > 150) {
            $errores['inspector'] = 'El inspector es obligatorio y admite hasta 150 caracteres.';
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        return [$fecha, $inspector];
    }
}
