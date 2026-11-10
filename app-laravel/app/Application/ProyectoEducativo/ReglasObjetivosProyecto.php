<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 3 (Objetivos Institucionales): los dos campos son requeridos y respetan su
 * rango de palabras. Se usa al guardar (todo o nada) y para saber si la sección está completa.
 */
final class ReglasObjetivosProyecto
{
    /** campo => [mínimo, máximo] de palabras. */
    public const RANGOS = ['objetivo_general' => [50, 100], 'objetivos_especificos' => [100, 250]];

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos): array
    {
        return ConteoPalabras::errores(self::RANGOS, $datos);
    }
}
