<?php

namespace App\Application\ProyectoEducativo;

use Closure;
use Illuminate\Support\Facades\Validator;

/**
 * Única definición de "palabra" del Proyecto Educativo: trozos separados por espacio en blanco (NBSP incluido), sin vacíos.
 * El contador del navegador (components/ui/textarea-palabras.blade.php) cuenta igual con split(/\s+/); el del servidor manda.
 */
final class ConteoPalabras
{
    public static function contar(?string $texto): int
    {
        return count(preg_split('/\s+/u', trim((string) $texto), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Regla de validación de rango de palabras. El máximo siempre aplica; el mínimo solo en modo "completa"
     * (un borrador puede quedarse corto). Va junto a `required`/`nullable`, así que solo corre con texto.
     */
    public static function regla(int $min, int $max, bool $completa): Closure
    {
        return function (string $campo, mixed $valor, Closure $falla) use ($min, $max, $completa): void {
            $palabras = self::contar($valor);
            if ($palabras > $max || ($completa && $palabras < $min)) {
                $falla("Debe tener entre {$min} y {$max} palabras (lleva {$palabras}).");
            }
        };
    }

    /**
     * Errores de campos de texto que solo tienen rango de palabras (secciones 2 y 3): en modo completa todos son
     * requeridos y respetan el mínimo; en borrador solo el máximo de lo capturado.
     *
     * @param  array<string, array{int, int}>  $rangos  campo => [mínimo, máximo]
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $rangos, array $datos, bool $completa): array
    {
        $reglas = [];
        foreach ($rangos as $campo => [$min, $max]) {
            $reglas[$campo] = [$completa ? 'required' : 'nullable', 'string', self::regla($min, $max, $completa)];
        }

        $validador = Validator::make($datos, $reglas, ['required' => 'Este campo es obligatorio.']);

        return array_map(fn (array $mensajes) => $mensajes[0], $validador->errors()->toArray());
    }
}
