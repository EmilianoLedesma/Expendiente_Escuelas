<?php

namespace App\Application\ProyectoEducativo;

use Illuminate\Support\Facades\Validator;

/**
 * Única lista de reglas de la sección 1 (Presentación del Proyecto Académico). Dos modos sobre las mismas reglas:
 * borrador (formatos y máximos de lo que esté capturado) y completa (además, todo lo requerido y el mínimo de
 * palabras). Guardar valida en borrador; "completa" es que pase en modo completa.
 */
final class ReglasPresentacionProyecto
{
    public const PALABRAS_MIN = 500;

    public const PALABRAS_MAX = 1000;

    /** Palabras = trozos separados por espacio en blanco, sin vacíos. */
    public static function contarPalabras(?string $texto): int
    {
        return count(preg_split('/\s+/u', trim((string) $texto), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (teléfonos en dígitos, vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos, bool $completa): array
    {
        $req = $completa ? 'required' : 'nullable';

        $validador = Validator::make($datos, [
            'fecha_entrega' => [$req, 'date_format:Y-m-d'],
            'nombre_institucion' => [$req, 'string', 'max:255'],
            'fecha_apertura' => [$req, 'date_format:Y-m-d', 'before_or_equal:today'],
            'domicilio_completo' => [$req, 'string', 'max:500'],
            'representante_legal' => [$req, 'string', 'max:255'],
            'correo' => [$req, 'email', 'max:255'],
            'telefono' => [$req, 'digits:10'],
            'celular' => ['nullable', 'digits:10'],
            'pagina_web' => ['nullable', 'url:http,https', 'max:255'],
            'modalidad' => [$req, 'in:presencial,virtual,mixta'],
            'ciclo_escolar' => [$req, 'in:semestral,cuatrimestral,trimestral,bimestral,mensual,otro'],
            'ciclo_escolar_otro' => ['nullable', ...($completa ? ['required_if:ciclo_escolar,otro'] : []), 'string', 'max:100'],
            'duracion' => [$req, 'in:3_anios,2_anios,otro'],
            'duracion_otro' => ['nullable', ...($completa ? ['required_if:duracion,otro'] : []), 'string', 'max:100'],
            'sintesis_factibilidad' => [$req, 'string', function (string $campo, mixed $valor, \Closure $falla) use ($completa) {
                $palabras = self::contarPalabras($valor);
                if ($palabras > self::PALABRAS_MAX || ($completa && $palabras < self::PALABRAS_MIN)) {
                    $falla('La síntesis debe tener entre '.self::PALABRAS_MIN.' y '.self::PALABRAS_MAX." palabras (lleva {$palabras}).");
                }
            }],
        ], [
            'required' => 'Este campo es obligatorio.',
            'required_if' => 'Este campo es obligatorio.',
            'max' => 'No debe pasar de :max caracteres.',
            'email' => 'Captura un correo electrónico válido.',
            'digits' => 'Captura un número de 10 dígitos.',
            'url' => 'Captura una dirección válida que empiece con http:// o https://.',
            'in' => 'Elige una opción de la lista.',
            'date_format' => 'Captura una fecha válida.',
            'before_or_equal' => 'La fecha no puede ser posterior a hoy.',
        ]);

        return array_map(fn (array $mensajes) => $mensajes[0], $validador->errors()->toArray());
    }
}
