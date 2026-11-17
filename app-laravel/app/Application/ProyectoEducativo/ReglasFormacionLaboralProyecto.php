<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 14 (Plan de Estudios, bloque "Componente y Enfoque de Formación Laboral"): las dos
 * primeras formaciones son requeridas (100 a 250 palabras; el rango no lo dio el dueño, es una propuesta aprobada) y
 * "Otras formaciones" es opcional: vacía es válida, y con texto respeta el mismo rango. Una sola página, un solo guardado.
 */
final class ReglasFormacionLaboralProyecto
{
    /** campo => [mínimo, máximo] de palabras. */
    public const RANGOS = [
        'formacion_laboral_1' => [100, 250],
        'formacion_laboral_2' => [100, 250],
        'otras_formaciones' => [100, 250],
    ];

    /** Campos que pueden quedar vacíos; con texto sí respetan su rango. */
    public const OPCIONALES = ['otras_formaciones'];

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos): array
    {
        $rangos = self::RANGOS;
        foreach (self::OPCIONALES as $campo) {
            if (($datos[$campo] ?? null) === null) {
                unset($rangos[$campo]);
            }
        }

        return ConteoPalabras::errores($rangos, $datos);
    }
}
