<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 18 (Calendario Académico): un PDF requerido (hasta 10 MB, sin tope de páginas) y una
 * descripción opcional de 50 a 250 palabras (vacía es válida). El rango de la descripción no lo dio el dueño: es una
 * propuesta aprobada.
 */
final class ReglasCalendarioAcademicoProyecto
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

        return ConteoPalabras::errores(['descripcion_calendario' => self::DESCRIPCION], ['descripcion_calendario' => $descripcion]);
    }
}
