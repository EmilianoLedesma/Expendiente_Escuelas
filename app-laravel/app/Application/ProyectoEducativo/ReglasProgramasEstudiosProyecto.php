<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 16 (Programas de Estudios): periodicidad (con texto propio si es "otra", hasta 60
 * caracteres), modalidad y "¿retoma los programas de la DGB completos?" son requeridas y de su lista. Solo si NO los
 * retoma, la justificación es requerida (200 a 500 palabras; el rango sí lo dio el dueño) y el PDF de los programas
 * adaptados también (lo revisa el caso de uso). Con "sí" esos dos campos no aplican.
 */
final class ReglasProgramasEstudiosProyecto
{
    /** clave => etiqueta del dueño. */
    public const PERIODICIDADES = [
        'semestral' => 'Semestral',
        'cuatrimestral' => 'Cuatrimestral',
        'trimestral' => 'Trimestral',
        'bimestral' => 'Bimestral',
        'mensual' => 'Mensual',
        'otra' => 'Otra',
    ];

    public const MODALIDADES = ['presencial' => 'Presencial', 'virtual' => 'Virtual', 'mixta' => 'Mixta'];

    public const RETOMA = ['si' => 'Sí', 'no' => 'No'];

    /** [mínimo, máximo] de palabras de la justificación. */
    public const JUSTIFICACION = [200, 500];

    /** Tope de caracteres del texto de "otra" periodicidad. */
    public const OTRA_MAX = 60;

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos): array
    {
        $errores = [];

        $periodicidad = $datos['periodicidad_ciclo'] ?? null;
        if (! is_string($periodicidad) || ! array_key_exists($periodicidad, self::PERIODICIDADES)) {
            $errores['periodicidad_ciclo'] = 'Elija una opción.';
        } elseif ($periodicidad === 'otra') {
            $otra = $datos['periodicidad_otra'] ?? null;
            if (! is_string($otra) || $otra === '') {
                $errores['periodicidad_otra'] = 'Este campo es obligatorio.';
            } elseif (mb_strlen($otra) > self::OTRA_MAX) {
                $errores['periodicidad_otra'] = 'No debe pasar de '.self::OTRA_MAX.' caracteres.';
            }
        }

        $modalidad = $datos['modalidad_programas'] ?? null;
        if (! is_string($modalidad) || ! array_key_exists($modalidad, self::MODALIDADES)) {
            $errores['modalidad_programas'] = 'Elija una opción.';
        }

        $retoma = $datos['retoma_dgb'] ?? null;
        if (! is_string($retoma) || ! array_key_exists($retoma, self::RETOMA)) {
            $errores['retoma_dgb'] = 'Elija una opción.';
        } elseif ($retoma === 'no') {
            $errores += ConteoPalabras::errores(['justificacion_adaptacion' => self::JUSTIFICACION], $datos);
        }

        return $errores;
    }
}
