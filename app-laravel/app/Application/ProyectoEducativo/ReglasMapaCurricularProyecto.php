<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 15 (Mapa Curricular): un PDF requerido (hasta 10 MB, sin tope de páginas: un mapa puede
 * ser una sola lámina grande) y una descripción opcional de 50 a 250 palabras (vacía es válida). El rango de la descripción
 * no lo dio el dueño: es una propuesta aprobada.
 */
final class ReglasMapaCurricularProyecto
{
    /** [mínimo, máximo] de palabras de la descripción cuando se escribe. */
    public const DESCRIPCION = [50, 250];

    /**
     * @param  string|null  $descripcion  ya normalizada (vacía como null)
     * @return array<string, string> campo => mensaje
     */
    public static function erroresDescripcion(?string $descripcion): array
    {
        if ($descripcion === null) {
            return [];
        }

        return ConteoPalabras::errores(['descripcion_mapa' => self::DESCRIPCION], ['descripcion_mapa' => $descripcion]);
    }
}
