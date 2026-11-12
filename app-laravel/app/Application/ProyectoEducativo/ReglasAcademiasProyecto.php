<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 12 (Programa de Academias Docentes): el tipo es una de seis opciones; con Mixta u
 * Otros la descripción es requerida (20 a 100 palabras) y con las demás no aplica; la justificación es requerida (100 a
 * 250 palabras). Todo es requerido (no hay guardado parcial). Se captura en dos bloques.
 */
final class ReglasAcademiasProyecto
{
    /** clave => etiqueta del dueño. */
    public const TIPOS = [
        'transversal' => 'Academia transversal o integral',
        'por_recurso_area' => 'Academias por recurso o área',
        'por_grados' => 'Academias por grados',
        'por_recursos_afines' => 'Academias por recursos o áreas afines',
        'mixta' => 'Mixta',
        'otros' => 'Otros',
    ];

    /** Tipos que piden la descripción. */
    public const REQUIEREN_DESCRIPCION = ['mixta', 'otros'];

    public const PALABRAS_DESCRIPCION = [20, 100];

    public const PALABRAS_JUSTIFICACION = [100, 250];

    /** número de bloque => título y campos que muestra. */
    public const BLOQUES = [
        1 => ['titulo' => 'Conformación de la(s) Academia(s) Docente(s)', 'campos' => ['tipo_academia', 'descripcion_academia']],
        2 => ['titulo' => 'Justificación de la composición de la(s) academia(s) docente(s)', 'campos' => ['justificacion_academia']],
    ];

    public static function requiereDescripcion(?string $tipo): bool
    {
        return in_array($tipo, self::REQUIEREN_DESCRIPCION, true);
    }

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos): array
    {
        $tipo = $datos['tipo_academia'] ?? null;
        $errores = [];
        $rangos = ['justificacion_academia' => self::PALABRAS_JUSTIFICACION];

        if (! is_string($tipo) || ! array_key_exists($tipo, self::TIPOS)) {
            $errores['tipo_academia'] = 'Elija una opción.';
        } elseif (self::requiereDescripcion($tipo)) {
            $rangos['descripcion_academia'] = self::PALABRAS_DESCRIPCION;
        }

        return $errores + ConteoPalabras::errores($rangos, $datos);
    }
}
