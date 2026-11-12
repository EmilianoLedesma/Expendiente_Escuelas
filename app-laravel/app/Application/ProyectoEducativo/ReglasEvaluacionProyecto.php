<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 5 (Modelo de Evaluación): los siete textos son requeridos y respetan su rango de
 * palabras (no hay guardado parcial). Los campos se capturan en tres bloques, uno por pantalla.
 */
final class ReglasEvaluacionProyecto
{
    /** campo => [mínimo, máximo] de palabras. */
    public const RANGOS = [
        'evaluacion_diagnostica' => [100, 300],
        'evaluacion_formativa' => [100, 300],
        'evaluacion_sumativa' => [100, 300],
        'heteroevaluacion' => [100, 300],
        'coevaluacion' => [100, 300],
        'autoevaluacion' => [100, 300],
        'elementos_porcentajes' => [200, 500],
    ];

    /** número de bloque => título y campos que muestra (en el orden del dueño). */
    public const BLOQUES = [
        1 => ['titulo' => 'Funciones del Proceso Evaluativo (100 a 300 palabras por función)', 'campos' => ['evaluacion_diagnostica', 'evaluacion_formativa', 'evaluacion_sumativa']],
        2 => ['titulo' => 'Estrategias de Evaluación Auténtica (100 a 300 palabras por cada tipo)', 'campos' => ['heteroevaluacion', 'coevaluacion', 'autoevaluacion']],
        3 => ['titulo' => 'Elementos y Porcentajes para la Calificación (200 a 500 palabras)', 'campos' => ['elementos_porcentajes']],
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
