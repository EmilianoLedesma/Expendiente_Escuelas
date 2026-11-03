<?php

namespace App\Application\Archivo;

use App\Application\Excepciones\DatosInvalidos;

/** Compuertas comunes de la purga (único borrado definitivo): confirmación escrita y motivo obligatorio. */
final class Purga
{
    public const CONFIRMACION = 'ELIMINAR';

    /** @throws DatosInvalidos */
    public static function validar(string $motivo, string $confirmacion): void
    {
        $errores = [];
        if ($confirmacion !== self::CONFIRMACION) {
            $errores['confirmacion'] = 'Escribe '.self::CONFIRMACION.' para confirmar la eliminación.';
        }
        if (trim($motivo) === '') {
            $errores['motivo'] = 'Escribe el motivo de la eliminación.';
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
