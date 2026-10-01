<?php

namespace App\Application\Captura;

/**
 * Limpieza de lo que escribe el solicitante, antes de validar y guardar.
 *
 * Livewire desactiva para sus peticiones los middleware TrimStrings y
 * ConvertEmptyStringsToNull de Laravel (Livewire\Mechanisms\HandleRequests),
 * así que sin esto un " Centro " o una CURP en minúsculas llegaban tal cual
 * a la base de datos.
 *
 * Nunca descarta contenido que la validación deba ver: una letra en un
 * teléfono se conserva para que la regla la rechace con un mensaje, en vez
 * de "corregirla" en silencio.
 */
final class NormalizadorEntrada
{
    /** Caracteres de control y de formato invisibles (ancho cero, BOM, marcas de dirección). */
    private const INVISIBLES = '/[\x{00AD}\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{200E}\x{200F}]/u';

    public static function aplicar(Normalizacion $como, string $valor): string
    {
        return match ($como) {
            Normalizacion::Texto => self::texto($valor),
            Normalizacion::TextoLargo => self::textoLargo($valor),
            Normalizacion::Identificador => mb_strtoupper(self::sinSeparadores(self::texto($valor), '.')),
            Normalizacion::Correo => mb_strtolower(self::sinSeparadores(self::texto($valor))),
            Normalizacion::Telefono => self::telefono($valor),
            Normalizacion::Digitos => self::sinSeparadores(self::texto($valor)),
            Normalizacion::Ninguna => $valor,
        };
    }

    private static function texto(string $valor): string
    {
        $valor = self::sinInvisibles($valor);

        return trim((string) preg_replace('/[\s\p{Z}\p{Cc}]+/u', ' ', $valor));
    }

    private static function textoLargo(string $valor): string
    {
        $valor = str_replace(["\r\n", "\r"], "\n", self::sinInvisibles($valor));
        $valor = (string) preg_replace('/[^\S\n]+|[\p{Z}\x{0000}-\x{0009}\x{000B}-\x{001F}\x{007F}]+/u', ' ', $valor);
        $valor = (string) preg_replace('/ *\n */', "\n", $valor);
        $valor = (string) preg_replace('/\n{3,}/', "\n\n", $valor);

        return trim($valor);
    }

    private static function telefono(string $valor): string
    {
        $valor = self::sinSeparadores(self::texto($valor), '.()');

        // Lada internacional de México escrita por el solicitante ("+52 442…").
        if (str_starts_with($valor, '+52')) {
            $valor = substr($valor, 3);
        }

        return $valor;
    }

    private static function sinSeparadores(string $valor, string $extra = ''): string
    {
        return str_replace([' ', '-', ...mb_str_split($extra)], '', $valor);
    }

    private static function sinInvisibles(string $valor): string
    {
        return (string) preg_replace(self::INVISIBLES, '', $valor);
    }
}
