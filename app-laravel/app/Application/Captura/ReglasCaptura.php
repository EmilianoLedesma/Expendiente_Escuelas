<?php

namespace App\Application\Captura;

use App\Domain\Captura\Formatos;
use Closure;

/**
 * Reglas de validación de captura reutilizables, con mensaje en español.
 * Los formularios Livewire las componen en sus rules(); los formatos salen
 * de App\Domain\Captura\Formatos, los mismos que revisan los casos de uso,
 * así que pantalla y backend no pueden divergir.
 *
 * Todas aceptan $requerido (true/false o una regla condicional como
 * "required_if:bifurcacion,nuevo") y empiezan con "bail": un campo muestra un solo mensaje, el primero
 * que falla, en vez de "es obligatorio" y "formato inválido" a la vez.
 * Los límites numéricos reflejan el tipo de la columna en el DDL, para que
 * un valor fuera de rango sea un mensaje en el campo y no un error de base
 * de datos (500).
 */
final class ReglasCaptura
{
    public const MAX_SMALLINT = 32767;

    public const MAX_INTEGER = 2147483647;

    /** NUMERIC(10,2) */
    public const MAX_NUMERIC_10_2 = 99999999.99;

    /** NUMERIC(8,2) */
    public const MAX_NUMERIC_8_2 = 999999.99;

    /** NUMERIC(6,2) */
    public const MAX_NUMERIC_6_2 = 9999.99;

    private const ANIO_MINIMO = 1900;

    private const ANIO_MAXIMO = 2100;

    /** @return list<mixed> */
    public static function curp(bool|string $requerido = false): array
    {
        return [...self::base($requerido), 'string', self::formato(
            Formatos::esCurp(...),
            'La :attribute no tiene un formato válido: son 18 caracteres, por ejemplo GOMA800101HQTRRL09.',
        )];
    }

    /** @return list<mixed> */
    public static function rfcPersonaFisica(bool|string $requerido = false): array
    {
        return [...self::base($requerido), 'string', self::formato(
            Formatos::esRfcPersonaFisica(...),
            'El :attribute no tiene un formato válido: son 13 caracteres para persona física, por ejemplo GOMA800101AB1.',
        )];
    }

    /** @return list<mixed> */
    public static function rfcPersonaMoral(bool|string $requerido = false): array
    {
        return [...self::base($requerido), 'string', self::formato(
            Formatos::esRfcPersonaMoral(...),
            'El :attribute no tiene un formato válido: son 12 caracteres para persona moral, por ejemplo ABC800101AB1.',
        )];
    }

    /**
     * RFC de persona física o moral, cuando el formulario no sabe de cuál se trata.
     *
     * @return list<mixed>
     */
    public static function rfc(bool|string $requerido = false): array
    {
        return [...self::base($requerido), 'string', self::formato(
            fn (string $valor): bool => Formatos::esRfcPersonaFisica($valor) || Formatos::esRfcPersonaMoral($valor),
            'El :attribute no tiene un formato válido: son 13 caracteres para persona física o 12 para persona moral.',
        )];
    }

    /** @return list<mixed> */
    public static function codigoPostal(bool|string $requerido = false): array
    {
        return [...self::base($requerido), 'string', self::formato(
            Formatos::esCodigoPostal(...),
            'El :attribute debe tener 5 dígitos, por ejemplo 76000.',
        )];
    }

    /** @return list<mixed> */
    public static function telefono(bool|string $requerido = false): array
    {
        return [...self::base($requerido), 'string', self::formato(
            Formatos::esTelefono(...),
            'El :attribute debe tener 10 dígitos, por ejemplo 4421234567.',
        )];
    }

    /** @return list<mixed> */
    public static function correo(bool|string $requerido = false, int $max = 150): array
    {
        return [...self::base($requerido), 'string', "max:{$max}", 'email:filter'];
    }

    /**
     * Nombre de una persona: letras (con acentos), espacios, puntos, apóstrofos y guiones.
     *
     * @return list<mixed>
     */
    public static function nombrePersona(bool|string $requerido = false, int $max = 200): array
    {
        return [...self::base($requerido), 'string', "max:{$max}", self::formato(
            fn (string $valor): bool => preg_match("/^\\p{L}[\\p{L}\\p{M} .'’-]*$/u", $valor) === 1,
            'El campo :attribute solo puede contener letras, espacios, puntos, apóstrofos y guiones.',
        )];
    }

    /** @return list<mixed> */
    public static function texto(bool|string $requerido = false, int $max = 200): array
    {
        return [...self::base($requerido), 'string', "max:{$max}"];
    }

    /**
     * Fecha que ya ocurrió: nacimiento, emisión de un documento, firma de un poder.
     *
     * @return list<mixed>
     */
    public static function fechaPasada(bool|string $requerido = false): array
    {
        return [...self::base($requerido), 'date_format:Y-m-d', self::enRango(hastaHoy: true)];
    }

    /**
     * Fecha que puede estar en el futuro (vigencias).
     *
     * @return list<mixed>
     */
    public static function fecha(bool|string $requerido = false): array
    {
        return [...self::base($requerido), 'date_format:Y-m-d', self::enRango(hastaHoy: false)];
    }

    /** @return list<mixed> */
    public static function entero(int $max = self::MAX_SMALLINT, int $min = 0, bool|string $requerido = false): array
    {
        return [...self::base($requerido), 'integer', "min:{$min}", "max:{$max}"];
    }

    /** @return list<mixed> */
    public static function decimal(float $max, int $decimales = 2, float $min = 0, bool|string $requerido = false): array
    {
        return [...self::base($requerido), 'numeric', "min:{$min}", "max:{$max}", self::formato(
            fn (string $valor): bool => preg_match('/^-?\d+(\.\d{0,'.$decimales.'})?$/', $valor) === 1,
            "El campo :attribute admite como máximo {$decimales} decimales.",
        )];
    }

    /**
     * $requerido: true, false, o una regla condicional ("required_if:...")
     * que se combina con nullable, como en cualquier regla de Laravel.
     *
     * @return list<string>
     */
    private static function base(bool|string $requerido): array
    {
        return match (true) {
            $requerido === true => ['bail', 'required'],
            $requerido === false => ['bail', 'nullable'],
            default => ['bail', $requerido, 'nullable'],
        };
    }

    /** @param Closure(string): bool $cumple */
    private static function formato(Closure $cumple, string $mensaje): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) use ($cumple, $mensaje): void {
            if (! is_scalar($valor) || ! $cumple((string) $valor)) {
                $fallar($mensaje);
            }
        };
    }

    private static function enRango(bool $hastaHoy): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) use ($hastaHoy): void {
            $anio = (int) substr((string) $valor, 0, 4);

            if ($anio < self::ANIO_MINIMO) {
                $fallar('La :attribute no puede ser anterior a '.self::ANIO_MINIMO.'.');

                return;
            }

            if ($hastaHoy && (string) $valor > now()->toDateString()) {
                $fallar('La :attribute no puede ser posterior a hoy.');

                return;
            }

            if ($anio > self::ANIO_MAXIMO) {
                $fallar('La :attribute no puede ser posterior a '.self::ANIO_MAXIMO.'.');
            }
        };
    }
}
