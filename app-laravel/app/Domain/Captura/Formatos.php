<?php

namespace App\Domain\Captura;

/**
 * Formato estructural de los datos de identificación y contacto que captura
 * el solicitante. Solo estructura (longitud, posiciones, fecha embebida
 * plausible): no calcula dígitos verificadores ni consulta RENAPO/SAT.
 *
 * Recibe el valor ya normalizado (mayúsculas, sin espacios ni guiones): la
 * normalización es de la capa de captura, aquí solo se decide si el valor
 * limpio es aceptable. Lo usan tanto las reglas de los formularios como los
 * casos de uso, para que la validación de la pantalla y la del backend no
 * puedan divergir.
 *
 * Única fuente de los patrones: el motor documental (ADR-007,
 * IdentificadorCoincide) revisa CURP y RFC con estas mismas constantes.
 */
final class Formatos
{
    /** 4 letras, AAMMDD, sexo (H/M/X), entidad (2), 3 consonantes internas, homoclave, dígito. */
    public const CURP = '/^[A-Z][AEIOUX][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HMX][A-Z]{2}[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d$/';

    /** Persona física: 4 letras, AAMMDD, homoclave de 3. */
    public const RFC_PERSONA_FISICA = '/^[A-ZÑ&]{4}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[A-Z\d]{3}$/u';

    /** Persona moral: 3 letras, AAMMDD, homoclave de 3. */
    public const RFC_PERSONA_MORAL = '/^[A-ZÑ&]{3}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[A-Z\d]{3}$/u';

    /** Física (13) o moral (12), cuando no se sabe de cuál se trata (constancia de situación fiscal, motor documental). */
    public const RFC = '/^[A-ZÑ&]{3,4}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[A-Z\d]{3}$/u';

    /** 5 dígitos; en México no existe el prefijo 00. */
    public const CODIGO_POSTAL = '/^(?!00)\d{5}$/';

    /** Número nacional a 10 dígitos (plan de numeración vigente desde 2019). */
    public const TELEFONO = '/^[1-9]\d{9}$/';

    public static function esCurp(string $valor): bool
    {
        return preg_match(self::CURP, $valor) === 1;
    }

    public static function esRfcPersonaFisica(string $valor): bool
    {
        return preg_match(self::RFC_PERSONA_FISICA, $valor) === 1;
    }

    public static function esRfcPersonaMoral(string $valor): bool
    {
        return preg_match(self::RFC_PERSONA_MORAL, $valor) === 1;
    }

    public static function esRfc(string $valor): bool
    {
        return preg_match(self::RFC, $valor) === 1;
    }

    public static function esCodigoPostal(string $valor): bool
    {
        return preg_match(self::CODIGO_POSTAL, $valor) === 1;
    }

    public static function esTelefono(string $valor): bool
    {
        return preg_match(self::TELEFONO, $valor) === 1;
    }

    /** Mismo criterio que la regla "email:filter" de los formularios. */
    public static function esCorreo(string $valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_EMAIL) !== false;
    }
}
