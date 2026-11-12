<?php

namespace App\Application\ProyectoEducativo;

/** Única lista de reglas de la sección 9 (Perfil de Egreso): el texto es requerido y respeta su rango de palabras. */
final class ReglasEgresoProyecto
{
    /** campo => [mínimo, máximo] de palabras. */
    public const RANGOS = ['perfil_egreso' => [100, 400]];

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos): array
    {
        return ConteoPalabras::errores(self::RANGOS, $datos);
    }
}
