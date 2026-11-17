<?php

namespace App\Application\ProyectoEducativo;

use InvalidArgumentException;

/**
 * Única lista de reglas de la sección 13 (Criterios de Acreditación, Promoción y Certificación): los cinco campos son
 * requeridos y respetan 100 a 200 palabras. Se capturan en dos bloques que se guardan por separado; cada guardado valida
 * solo los campos de su bloque. La sección está completa cuando los cinco campos guardados cumplen.
 */
final class ReglasCriteriosProyecto
{
    /** campo => [mínimo, máximo] de palabras. */
    public const RANGOS = [
        'criterios_acreditacion' => [100, 200],
        'opciones_acreditacion' => [100, 200],
        'criterios_promocion' => [100, 200],
        'criterios_titulacion' => [100, 200],
        'servicios_control_escolar' => [100, 200],
    ];

    /** número de bloque => título y campos que muestra (en el orden del dueño). */
    public const BLOQUES = [
        1 => ['titulo' => 'Acreditación y promoción', 'campos' => ['criterios_acreditacion', 'opciones_acreditacion', 'criterios_promocion']],
        2 => ['titulo' => 'Titulación y control escolar', 'campos' => ['criterios_titulacion', 'servicios_control_escolar']],
    ];

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @param  int|null  $bloque  null = los cinco campos (sección completa)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos, ?int $bloque = null): array
    {
        if ($bloque === null) {
            return ConteoPalabras::errores(self::RANGOS, $datos);
        }

        $campos = (self::BLOQUES[$bloque] ?? throw new InvalidArgumentException("Bloque de criterios desconocido: {$bloque}"))['campos'];

        return ConteoPalabras::errores(array_intersect_key(self::RANGOS, array_flip($campos)), $datos);
    }
}
