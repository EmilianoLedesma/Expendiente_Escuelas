<?php

namespace App\Application\ProyectoEducativo;

use InvalidArgumentException;

/**
 * Única lista de reglas de la sección 19 (Servicios de Biblioteca y Acervo Bibliográfico). Todo texto es requerido y de 80 a
 * 250 palabras ("por rubro"). Condicionales: el tipo de biblioteca virtual solo con biblioteca virtual o mixta, el perfil
 * del encargado solo con encargado y el texto del programa de lectura solo con programa. Se captura en dos bloques que se
 * guardan por separado; cada guardado valida solo los campos de su bloque, y la sección está completa con los dos válidos.
 */
final class ReglasBibliotecaProyecto
{
    /** clave => etiqueta del dueño. */
    public const TIPOS = ['fisica' => 'Física', 'virtual' => 'Virtual', 'mixta' => 'Mixta'];

    public const TIPOS_VIRTUAL = ['propia' => 'Propia', 'dominio_publico' => 'De dominio público', 'mixta' => 'Mixta'];

    public const SI_NO = ['si' => 'Sí', 'no' => 'No'];

    /** [mínimo, máximo] de palabras de cada texto. */
    public const PALABRAS = [80, 250];

    /** número de bloque => título y campos que muestra. */
    public const BLOQUES = [
        1 => ['titulo' => 'Datos generales y perfiles', 'campos' => ['biblioteca_tipo', 'biblioteca_virtual_tipo', 'biblioteca_tiene_encargado', 'biblioteca_perfil_encargado', 'biblioteca_prestamo']],
        2 => ['titulo' => 'Otros servicios y acervo', 'campos' => ['biblioteca_tiene_programa_lectura', 'biblioteca_programa_lectura', 'biblioteca_otros_programas', 'acervo_clasificacion', 'acervo_titulos_basicos', 'acervo_titulos_lectura']],
    ];

    /**
     * @param  array<string, mixed>  $datos  ya normalizados (vacíos como null)
     * @param  int|null  $bloque  null = los dos bloques (sección completa)
     * @return array<string, string> campo => primer mensaje
     */
    public static function errores(array $datos, ?int $bloque = null): array
    {
        if ($bloque !== null && ! isset(self::BLOQUES[$bloque])) {
            throw new InvalidArgumentException("Bloque de biblioteca desconocido: {$bloque}");
        }

        $errores = [];
        if ($bloque === null || $bloque === 1) {
            $errores += self::erroresBloqueUno($datos);
        }
        if ($bloque === null || $bloque === 2) {
            $errores += self::erroresBloqueDos($datos);
        }

        return $errores;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, string>
     */
    private static function erroresBloqueUno(array $datos): array
    {
        $errores = [];

        $tipo = $datos['biblioteca_tipo'] ?? null;
        if (! is_string($tipo) || ! array_key_exists($tipo, self::TIPOS)) {
            $errores['biblioteca_tipo'] = 'Elija una opción.';
        } elseif ($tipo !== 'fisica') {
            $virtual = $datos['biblioteca_virtual_tipo'] ?? null;
            if (! is_string($virtual) || ! array_key_exists($virtual, self::TIPOS_VIRTUAL)) {
                $errores['biblioteca_virtual_tipo'] = 'Elija una opción.';
            }
        }

        $textos = ['biblioteca_prestamo'];
        $encargado = $datos['biblioteca_tiene_encargado'] ?? null;
        if (! is_string($encargado) || ! array_key_exists($encargado, self::SI_NO)) {
            $errores['biblioteca_tiene_encargado'] = 'Elija una opción.';
        } elseif ($encargado === 'si') {
            $textos[] = 'biblioteca_perfil_encargado';
        }

        return $errores + self::erroresDeTextos($textos, $datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, string>
     */
    private static function erroresBloqueDos(array $datos): array
    {
        $errores = [];

        $textos = ['biblioteca_otros_programas', 'acervo_clasificacion', 'acervo_titulos_basicos', 'acervo_titulos_lectura'];
        $programa = $datos['biblioteca_tiene_programa_lectura'] ?? null;
        if (! is_string($programa) || ! array_key_exists($programa, self::SI_NO)) {
            $errores['biblioteca_tiene_programa_lectura'] = 'Elija una opción.';
        } elseif ($programa === 'si') {
            $textos[] = 'biblioteca_programa_lectura';
        }

        return $errores + self::erroresDeTextos($textos, $datos);
    }

    /**
     * @param  list<string>  $campos
     * @param  array<string, mixed>  $datos
     * @return array<string, string>
     */
    private static function erroresDeTextos(array $campos, array $datos): array
    {
        return ConteoPalabras::errores(array_fill_keys($campos, self::PALABRAS), $datos);
    }
}
