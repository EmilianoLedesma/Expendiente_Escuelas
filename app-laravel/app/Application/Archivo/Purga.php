<?php

namespace App\Application\Archivo;

use App\Application\Excepciones\DatosInvalidos;

/** Compuertas comunes de la purga (único borrado definitivo): confirmación escrita y motivo obligatorio. */
final class Purga
{
    public const CONFIRMACION = 'PURGAR';

    /** @throws DatosInvalidos */
    public static function validar(string $motivo, string $confirmacion): void
    {
        $errores = [];
        if ($confirmacion !== self::CONFIRMACION) {
            $errores['confirmacion'] = 'Escribe '.self::CONFIRMACION.' para confirmar la purga.';
        }
        if (trim($motivo) === '') {
            $errores['motivo'] = 'Escribe el motivo de la purga.';
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
