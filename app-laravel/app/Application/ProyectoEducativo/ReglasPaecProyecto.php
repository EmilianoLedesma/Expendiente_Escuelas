<?php

namespace App\Application\ProyectoEducativo;

/** Única lista de reglas de la sección 6 (PAEC): diagnóstico con rango de palabras y dos PDF de 1 a 2 páginas, todo requerido. */
final class ReglasPaecProyecto
{
    /** [mínimo, máximo] de palabras del diagnóstico. */
    public const DIAGNOSTICO = [150, 500];

    /** [mínimo, máximo] de páginas de cada PDF. */
    public const PAGINAS = [1, 2];

    /** Tope de tamaño de cada PDF, igual que los documentos del Paso 2. */
    public const MAX_MB = 10;

    /**
     * @param  string|null  $diagnostico  ya normalizado (vacío como null)
     * @return array<string, string> campo => mensaje
     */
    public static function erroresDiagnostico(?string $diagnostico): array
    {
        return ConteoPalabras::errores(['diagnostico' => self::DIAGNOSTICO], ['diagnostico' => $diagnostico]);
    }
}
