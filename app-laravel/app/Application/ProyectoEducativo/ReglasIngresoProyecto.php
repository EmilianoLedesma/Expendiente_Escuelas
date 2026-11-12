<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 8 (Perfil de Ingreso): los cuatro campos son requeridos y respetan su rango de
 * palabras (no hay guardado parcial). El rango de "Instrumentos de Evaluación" no lo dio el dueño: se acordó 100 a 250.
 */
final class ReglasIngresoProyecto
{
    /** campo => [mínimo, máximo] de palabras. */
    public const RANGOS = [
        'perfil_ingreso' => [50, 150],
        'mecanismo_ingreso' => [100, 250],
        'costo_usuario' => [30, 100],
        'instrumentos_evaluacion' => [100, 250],
    ];

    /** número de bloque => título y campos que muestra (en el orden del dueño). */
    public const BLOQUES = [
        1 => ['titulo' => 'Perfil y Mecanismo de Ingreso', 'campos' => ['perfil_ingreso', 'mecanismo_ingreso']],
        2 => ['titulo' => 'Costo e Instrumentos de Evaluación', 'campos' => ['costo_usuario', 'instrumentos_evaluacion']],
    ];

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos): array
    {
        return ConteoPalabras::errores(self::RANGOS, $datos);
    }
}
