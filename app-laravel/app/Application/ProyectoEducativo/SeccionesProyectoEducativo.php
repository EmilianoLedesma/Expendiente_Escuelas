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
