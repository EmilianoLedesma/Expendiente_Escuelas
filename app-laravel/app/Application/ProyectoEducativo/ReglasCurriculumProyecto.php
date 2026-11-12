<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 7 (Currículum Ampliado): por cada ámbito, la respuesta es 'si' o 'no' y la
 * justificación (también con 'no') tiene de 100 a 250 palabras. Todo es requerido (no hay guardado parcial). Los cinco
 * ámbitos se capturan uno por pantalla.
 */
final class ReglasCurriculumProyecto
{
    /** [mínimo, máximo] de palabras de cada justificación. */
    public const PALABRAS = [100, 250];

    public const RESPUESTAS = ['si', 'no'];

    /** ámbito => título (en el orden del dueño). */
    public const AMBITOS = [
        'practica_ciudadana' => 'Práctica y Colaboración Ciudadana',
        'salud' => 'Educación para la Salud',
        'actividad_fisica' => 'Actividades Físicas y Deportivas',
        'sexualidad_genero' => 'Educación Integral en Sexualidad y Género',
        'artistico_cultural' => 'Actividades Artísticas y Culturales',
    ];

    /** número de bloque => título y campos que muestra. */
    public const BLOQUES = [
        1 => ['titulo' => 'Práctica y Colaboración Ciudadana', 'campos' => ['practica_ciudadana_trabaja', 'practica_ciudadana_justificacion']],
        2 => ['titulo' => 'Educación para la Salud', 'campos' => ['salud_trabaja', 'salud_justificacion']],
        3 => ['titulo' => 'Actividades Físicas y Deportivas', 'campos' => ['actividad_fisica_trabaja', 'actividad_fisica_justificacion']],
        4 => ['titulo' => 'Educación Integral en Sexualidad y Género', 'campos' => ['sexualidad_genero_trabaja', 'sexualidad_genero_justificacion']],
        5 => ['titulo' => 'Actividades Artísticas y Culturales', 'campos' => ['artistico_cultural_trabaja', 'artistico_cultural_justificacion']],
    ];

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos): array
    {
        $errores = [];
        $rangos = [];
        foreach (array_keys(self::AMBITOS) as $ambito) {
            if (! in_array($datos["{$ambito}_trabaja"] ?? null, self::RESPUESTAS, true)) {
                $errores["{$ambito}_trabaja"] = 'Elija Sí o No.';
            }
            $rangos["{$ambito}_justificacion"] = self::PALABRAS;
        }

        return $errores + ConteoPalabras::errores($rangos, $datos);
    }
}
