<?php

namespace App\Application\ProyectoEducativo;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Única lista de reglas de la sección 11, bloque "Formación Docente Inicial": la política (100 a 250 palabras) y de 1 a 20
 * acciones. Los rangos de los textos de la acción no los dio el dueño: son una propuesta que aprobó. Las horas se escriben
 * "HH:MM", las fechas "AAAA-MM-DD" (lo que mandan los campos nativos) y el fin va después del inicio.
 */
final class ReglasFormacionProyecto
{
    public const TIPO_INICIAL = 'inicial';

    public const TIPO_CONTINUA = 'continua';

    /** [mínimo, máximo] de palabras de la política. */
    public const POLITICA = [100, 250];

    public const MAX_ACCIONES = 20;

    /** campo => [mínimo, máximo] de palabras; todos requeridos. */
    public const TEXTOS = [
        'proposito' => [10, 100],
        'participantes' => [5, 50],
        'temario' => [20, 150],
        'evaluacion' => [10, 100],
        'recursos' => [5, 100],
        'perfil_ponente' => [10, 100],
        'reconocimiento' => [3, 50],
    ];

    /** "Otros" es opcional: [mínimo, máximo] aplican solo si lo escriben (el mínimo no se exige). */
    public const OTROS = [1, 100];

    public const FORMATOS = ['taller', 'curso', 'seminario', 'diplomado', 'otro'];

    public const DIAS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    public const CAMPOS_ACCION = [
        'nombre', 'proposito', 'participantes', 'formato', 'formato_otro', 'duracion_horas', 'dias', 'hora_inicio', 'hora_fin',
        'fecha_inicio', 'fecha_fin', 'temario', 'evaluacion', 'recursos', 'perfil_ponente', 'reconocimiento', 'otros',
    ];

    /** @return array<string, mixed> una acción en blanco, como la arranca el formulario */
    public static function accionVacia(): array
    {
        $accion = array_fill_keys(self::CAMPOS_ACCION, '');
        $accion['dias'] = [];

        return $accion;
    }

    /**
     * Texto recortado (vacío = null) en cada campo; lo que no es texto cuenta como vacío; los días son una lista de textos;
     * el texto de "Otro" solo se conserva si el formato es "otro".
     *
     * @return array{politica: string|null, acciones: list<array<string, mixed>>}
     */
    public static function normalizar(mixed $politica, mixed $acciones): array
    {
        $texto = fn (mixed $valor): ?string => is_scalar($valor) && ($valor = trim((string) $valor)) !== '' ? $valor : null;

        $limpias = [];
        foreach (is_array($acciones) ? $acciones : [] as $accion) {
            $accion = is_array($accion) ? $accion : [];
            $limpia = [];
            foreach (self::CAMPOS_ACCION as $campo) {
                $limpia[$campo] = $campo === 'dias'
                    ? array_values(array_filter(array_map($texto, is_array($accion['dias'] ?? null) ? $accion['dias'] : []), fn ($d) => $d !== null))
                    : $texto($accion[$campo] ?? null);
            }
            if ($limpia['formato'] !== 'otro') {
                $limpia['formato_otro'] = null;
            }
            $limpias[] = $limpia;
        }

        return ['politica' => $texto($politica), 'acciones' => $limpias];
    }

    /**
     * Errores de un bloque (política + acciones). Los nombres de las claves distinguen los bloques: el inicial usa
     * `politica_inicial` y `acciones`; el continuo, `politica_continua` y `acciones_continua`.
     *
     * @param  array{politica: string|null, acciones: list<array<string, mixed>>}  $bloque  ya normalizado
     * @return array<string, string> clave ($campoPolitica, $campoAcciones o "$campoAcciones.N.campo") => primer mensaje
     */
    public static function errores(array $bloque, string $campoPolitica = 'politica_inicial', string $campoAcciones = 'acciones'): array
    {
        $errores = ConteoPalabras::errores([$campoPolitica => self::POLITICA], [$campoPolitica => $bloque['politica']]);

        $total = count($bloque['acciones']);
        if ($total < 1) {
            return $errores + [$campoAcciones => 'Agregue al menos una acción.'];
        }
        if ($total > self::MAX_ACCIONES) {
            return $errores + [$campoAcciones => 'Puede agregar como máximo '.self::MAX_ACCIONES.' acciones.'];
        }

        foreach ($bloque['acciones'] as $i => $accion) {
            foreach (self::erroresAccion($accion) as $campo => $mensaje) {
                $errores["{$campoAcciones}.{$i}.{$campo}"] = $mensaje;
            }
        }

        return $errores;
    }

    /**
     * @param  array<string, mixed>  $accion  ya normalizada
     * @return array<string, string> campo => primer mensaje
     */
    public static function erroresAccion(array $accion): array
    {
        $reglas = [
            'nombre' => ['required', 'string', 'max:150'],
            'formato' => ['required', Rule::in(self::FORMATOS)],
            'formato_otro' => ['required_if:formato,otro', 'nullable', 'string', 'max:60'],
            'duracion_horas' => ['required', 'integer', 'between:1,999'],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['string', Rule::in(self::DIAS), 'distinct'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d'],
            'otros' => ['nullable', 'string', ConteoPalabras::regla(self::OTROS[0], self::OTROS[1], completa: false)],
        ];
        foreach (self::TEXTOS as $campo => [$min, $max]) {
            $reglas[$campo] = ['required', 'string', ConteoPalabras::regla($min, $max, completa: true)];
        }

        $mensajes = [
            'required' => 'Este campo es obligatorio.',
            'required_if' => 'Especifique el formato.',
            'in' => 'Elija una opción de la lista.',
            'max.string' => 'Escriba como máximo :max caracteres.',
            'integer' => 'Escriba un número entero de horas.',
            'between.numeric' => 'La duración debe ser de :min a :max horas.',
            'array' => 'Elija al menos un día.',
            'min.array' => 'Elija al menos un día.',
            'dias.*.string' => 'Día no válido.',
            'dias.*.in' => 'Día no válido.',
            'dias.*.distinct' => 'Día repetido.',
            'hora_inicio.date_format' => 'Escriba la hora como HH:MM.',
            'hora_fin.date_format' => 'Escriba la hora como HH:MM.',
            'fecha_inicio.date_format' => 'Escriba una fecha válida.',
            'fecha_fin.date_format' => 'Escriba una fecha válida.',
        ];

        $validador = Validator::make($accion, $reglas, $mensajes);
        $errores = array_map(fn (array $m) => $m[0], $validador->errors()->toArray());

        if (! isset($errores['hora_inicio']) && ! isset($errores['hora_fin']) && $accion['hora_fin'] <= $accion['hora_inicio']) {
            $errores['hora_fin'] = 'La hora de fin debe ser posterior a la de inicio.';
        }
        if (! isset($errores['fecha_inicio']) && ! isset($errores['fecha_fin']) && $accion['fecha_fin'] < $accion['fecha_inicio']) {
            $errores['fecha_fin'] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }

        return $errores;
    }
}
