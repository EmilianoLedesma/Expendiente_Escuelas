<?php

namespace App\Application\ProyectoEducativo;

use Illuminate\Support\Facades\Validator;

/**
 * Única lista de reglas de la sección 4 (Enfoque Pedagógico): dos textos con rango de palabras y dos listas de 2 a 5
 * ejemplos, cada ejemplo con su propio rango. Todo es requerido (no hay guardado parcial). Se usa al guardar y para
 * saber si la sección está completa.
 */
final class ReglasEnfoqueProyecto
{
    /** campo => [mínimo, máximo] de palabras. */
    public const RANGOS = ['propuesta_global' => [100, 200], 'apropiacion_enfoque' => [450, 600]];

    /** lista => nombre en plural para el mensaje. */
    public const LISTAS = ['estrategias_centrales' => 'estrategias', 'actividades_sustantivas' => 'actividades'];

    /** [mínimo, máximo] de ejemplos por lista. */
    public const EJEMPLOS = [2, 5];

    /** [mínimo, máximo] de palabras de cada ejemplo. */
    public const PALABRAS_EJEMPLO = [20, 80];

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo (o lista.índice) => primer mensaje
     */
    public static function errores(array $datos): array
    {
        [$minEj, $maxEj] = self::EJEMPLOS;
        [$minPal, $maxPal] = self::PALABRAS_EJEMPLO;

        $reglas = [];
        $mensajes = ['required' => 'Este campo es obligatorio.'];
        foreach (self::RANGOS as $campo => [$min, $max]) {
            $reglas[$campo] = ['bail', 'required', 'string', ConteoPalabras::regla($min, $max, completa: true)];
        }
        foreach (self::LISTAS as $lista => $plural) {
            $reglas[$lista] = ['bail', 'required', 'array', "min:{$minEj}", "max:{$maxEj}"];
            $reglas["{$lista}.*"] = ['bail', 'required', 'string', ConteoPalabras::regla($minPal, $maxPal, completa: true)];
            foreach (['required', 'array', 'min', 'max'] as $regla) {
                $mensajes["{$lista}.{$regla}"] = "Agrega entre {$minEj} y {$maxEj} {$plural}.";
            }
        }

        $errores = Validator::make($datos, $reglas, $mensajes)->errors()->toArray();

        return array_map(fn (array $m) => $m[0], $errores);
    }
}
