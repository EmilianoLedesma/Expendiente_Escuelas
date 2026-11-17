<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 17 (Planeación Académica): un PDF requerido con los tres ejemplos (hasta 10 MB, sin
 * tope de páginas) y una descripción requerida de 50 a 250 palabras que indica los campos disciplinares y semestres de los
 * ejemplos. El rango de la descripción no lo dio el dueño: es una propuesta aprobada.
 */
final class ReglasPlaneacionAcademicaProyecto
{
    /** [mínimo, máximo] de palabras de la descripción. */
    public const DESCRIPCION = [50, 250];

    /**
     * @param  string|null  $descripcion  ya normalizada (vacía como null)
     * @return array<string, string> campo => mensaje
     */
    public static function erroresDescripcion(?string $descripcion): array
    {
        return ConteoPalabras::errores(['descripcion_planeacion' => self::DESCRIPCION], ['descripcion_planeacion' => $descripcion]);
    }
}
