<?php

namespace App\Application\ProyectoEducativo;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Las 20 secciones de "Presentación del Proyecto Educativo" (sub-paso del
 * Paso 3, solo media superior), en el orden del índice del dueño.
 */
final class SeccionesProyectoEducativo
{
    public const CLAVE_PASO = 'proyecto_educativo';

    public const NIVEL = 'media_superior';

    private const NOMBRES = [
        'Presentación del Proyecto Académico',
        'Filosofía Institucional',
        'Objetivos Institucionales',
        'Enfoque Pedagógico',
        'Modelo de Evaluación',
        'Programa Aula, Escuela y Comunidad (PAEC)',
        'Currículum Ampliado',
        'Perfil de Ingreso',
        'Perfil de Egreso',
        'Perfil Docente',
        'Formación Docente',
        'Programa de Academias Docentes',
        'Criterios de Acreditación, Promoción y Certificación',
        'Plan de Estudios',
        'Mapa Curricular',
        'Programas de Estudios',
        'Planeación Académica',
        'Calendario Académico',
        'Servicios de Biblioteca y Acervo Bibliográfico',
        'Bibliografía del Proyecto',
    ];

    public static function aplicaA(string $nivelClave): bool
    {
        return $nivelClave === self::NIVEL;
    }

    /**
     * @return array<string, string> slug => nombre
     */
    public static function todas(): array
    {
        $todas = [];
        foreach (self::NOMBRES as $nombre) {
            $todas[Str::slug($nombre)] = $nombre;
        }

        return $todas;
    }

    /** Slug de la sección 1 (Presentación del Proyecto Académico), la única con formulario por ahora. */
    public static function presentacion(): string
    {
        return array_key_first(self::todas());
    }

    /** Slug de la sección 2 (Filosofía Institucional). */
    public static function filosofia(): string
    {
        return array_keys(self::todas())[1];
    }

    /** Slug de la sección 3 (Objetivos Institucionales). */
    public static function objetivos(): string
    {
        return array_keys(self::todas())[2];
    }

    /** Slug de la sección 4 (Enfoque Pedagógico). */
    public static function enfoque(): string
    {
        return array_keys(self::todas())[3];
    }

    /** Slug de la sección 5 (Modelo de Evaluación). */
    public static function evaluacion(): string
    {
        return array_keys(self::todas())[4];
    }

    /** Slug de la sección 6 (Programa Aula, Escuela y Comunidad). */
    public static function paec(): string
    {
        return array_keys(self::todas())[5];
    }

    /** Slug de la sección 7 (Currículum Ampliado). */
    public static function curriculum(): string
    {
        return array_keys(self::todas())[6];
    }

    /** Slug de la sección 8 (Perfil de Ingreso). */
    public static function ingreso(): string
    {
        return array_keys(self::todas())[7];
    }

    /** Slug de la sección 9 (Perfil de Egreso). */
    public static function egreso(): string
    {
        return array_keys(self::todas())[8];
    }

    /** Slug de la sección 10 (Perfil Docente). */
    public static function perfilDocente(): string
    {
        return array_keys(self::todas())[9];
    }

    /** Slug de la sección 11 (Formación Docente). */
    public static function formacion(): string
    {
        return array_keys(self::todas())[10];
    }

    /** Slug de la sección 12 (Programa de Academias Docentes). */
    public static function academias(): string
    {
        return array_keys(self::todas())[11];
    }

    public static function existe(string $slug): bool
    {
        return array_key_exists($slug, self::todas());
    }

    public static function nombre(string $slug): string
    {
        return self::todas()[$slug]
            ?? throw new InvalidArgumentException("Sección de proyecto educativo desconocida: {$slug}");
    }
}
