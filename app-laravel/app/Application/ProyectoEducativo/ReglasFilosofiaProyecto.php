<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 2 (Filosofía Institucional), en los mismos dos modos que la sección 1:
 * borrador (el máximo de palabras de lo capturado) y completa (además, los tres campos y su mínimo).
 */
final class ReglasFilosofiaProyecto
{
    /** campo => [mínimo, máximo] de palabras. */
    public const RANGOS = ['mision' => [50, 150], 'vision' => [50, 150], 'valores' => [50, 250]];

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos, bool $completa): array
    {
        return ConteoPalabras::errores(self::RANGOS, $datos, $completa);
    }
}
