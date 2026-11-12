<?php

namespace App\Application\ProyectoEducativo;

/** Única lista de reglas de la sección 10 (Perfil Docente): el texto es requerido y respeta su rango de palabras. */
final class ReglasPerfilDocenteProyecto
{
    /** campo => [mínimo, máximo] de palabras. */
    public const RANGOS = ['habilidades_virtudes' => [200, 400]];

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos): array
    {
        return ConteoPalabras::errores(self::RANGOS, $datos);
    }
}
