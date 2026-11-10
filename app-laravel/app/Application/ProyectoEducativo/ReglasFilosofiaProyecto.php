<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 2 (Filosofía Institucional): los tres campos son requeridos y respetan su
 * rango de palabras. Se usa al guardar (todo o nada) y para saber si la sección está completa.
 */
final class ReglasFilosofiaProyecto
{
    /** campo => [mínimo, máximo] de palabras. */
    public const RANGOS = ['mision' => [50, 150], 'vision' => [50, 150], 'valores' => [50, 250]];

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos): array
    {
        return ConteoPalabras::errores(self::RANGOS, $datos);
    }
}
