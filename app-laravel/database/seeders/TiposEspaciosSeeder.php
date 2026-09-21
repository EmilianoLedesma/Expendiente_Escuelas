<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de tipos de espacios y su aplicabilidad por nivel educativo
 * (COMPENDIO, "Infraestructura del nivel educativo", desglose confirmado
 * líneas 129-134, más la comparación Inicial-vs-genérico líneas 208-216).
 *
 * Ningún espacio se siembra como obligatorio: COMPENDIO enumera los
 * espacios capturables pero nunca marca ninguno como obligatorio. La
 * columna existe en el DDL para cuando SEDEQ confirme esa matriz.
 *
 * Donde COMPENDIO no dice a qué niveles aplica un espacio, se mapea a los
 * 4 niveles de Básica como no obligatorio; esas filas inferidas están
 * listadas en docs/decisions/PENDIENTE-matriz-espacios-por-nivel.md.
 */
class TiposEspaciosSeeder extends Seeder
{
    /** Claves cuya aplicabilidad por nivel está afirmada explícitamente en COMPENDIO. */
    private const APLICABILIDAD_EXPLICITA = [
        'filtro_recepcion' => ['inicial'],
        'subdireccion' => ['preescolar', 'primaria', 'secundaria'],
        'atencion_publico' => ['preescolar', 'primaria', 'secundaria'],
        'bodega' => ['preescolar', 'primaria', 'secundaria'],
        'sala_maestros' => ['preescolar', 'primaria', 'secundaria'],
        'biblioteca' => ['primaria', 'secundaria'],
        'taller' => ['preescolar', 'primaria', 'secundaria'],
        'laboratorio_polifuncional' => ['preescolar', 'primaria', 'secundaria'],
        'salon_usos_multiples' => ['preescolar', 'primaria', 'secundaria'],
        'auditorio' => ['preescolar', 'primaria', 'secundaria'],
        'cocina' => ['preescolar', 'primaria', 'secundaria'],
        'comedor' => ['preescolar', 'primaria', 'secundaria'],
        'sala_artes' => ['preescolar', 'primaria', 'secundaria'],
    ];

    private const NIVELES_BASICA = ['inicial', 'preescolar', 'primaria', 'secundaria'];

    public function run(): void
    {
        DB::table('tipos_espacios')->insertOrIgnore($this->tipos());

        $idsNivel = DB::table('niveles_educativos')
            ->whereIn('clave', self::NIVELES_BASICA)
            ->pluck('id', 'clave');

        $idsTipo = DB::table('tipos_espacios')->pluck('id', 'clave');

        $mapeos = [];

        foreach ($this->tipos() as $tipo) {
            $clave = $tipo['clave'];
            $niveles = self::APLICABILIDAD_EXPLICITA[$clave] ?? self::NIVELES_BASICA;

            foreach ($niveles as $claveNivel) {
                $mapeos[] = [
                    'nivel_educativo_id' => $idsNivel[$claveNivel],
                    'tipo_espacio_id' => $idsTipo[$clave],
                    'obligatorio' => false,
                ];
            }
        }

        DB::table('niveles_tipos_espacios')->insertOrIgnore($mapeos);
    }

    /** @return list<array{clave: string, nombre: string, categoria: string, permite_campo_futbol: bool, permite_material_biblioteca: bool}> */
    private function tipos(): array
    {
        $fila = fn (string $clave, string $nombre, string $categoria, bool $futbol = false, bool $biblioteca = false): array => [
            'clave' => $clave,
            'nombre' => $nombre,
            'categoria' => $categoria,
            'permite_campo_futbol' => $futbol,
            'permite_material_biblioteca' => $biblioteca,
        ];

        return [
            // Espacios administrativos (COMPENDIO línea 129 + línea 210).
            $fila('direccion', 'Dirección', 'administrativo'),
            $fila('subdireccion', 'Subdirección', 'administrativo'),
            $fila('oficinas_administrativas', 'Oficinas administrativas', 'administrativo'),
            $fila('control_escolar', 'Control escolar', 'administrativo'),
            $fila('atencion_publico', 'Atención al público', 'administrativo'),
            $fila('bodega', 'Bodega', 'administrativo'),
            $fila('sala_maestros', 'Sala de maestros', 'administrativo'),
            $fila('filtro_recepcion', 'Filtro / Recepción', 'administrativo'),

            // Cubículos (COMPENDIO línea 131): número, superficie y "destinado a" libre.
            $fila('cubiculo', 'Cubículo de atención', 'cubiculo'),

            // Instalaciones para actividades físicas y recreativas (COMPENDIO línea 133).
            $fila('cancha_usos_multiples', 'Cancha de usos múltiples', 'recreativo_deportivo'),
            $fila('chapoteadero', 'Chapoteadero', 'recreativo_deportivo'),
            $fila('arenero', 'Arenero', 'recreativo_deportivo'),
            $fila('zona_juegos', 'Zona de juegos', 'recreativo_deportivo'),
            $fila('areas_verdes', 'Áreas verdes', 'recreativo_deportivo'),
            $fila('area_recreo', 'Área de recreo', 'recreativo_deportivo'),
            $fila('campo_futbol', 'Campo de fútbol', 'recreativo_deportivo', futbol: true),
            $fila('otra_recreativa', 'Otra instalación recreativa', 'recreativo_deportivo'),

            // Instalaciones adicionales / especiales (COMPENDIO línea 134; Auditorio
            // reclasificado aquí el 2026-09-11, no en recreativo_deportivo).
            $fila('biblioteca', 'Centro de documentación / Biblioteca', 'especial', biblioteca: true),
            $fila('taller', 'Taller', 'especial'),
            $fila('laboratorio_polifuncional', 'Laboratorio polifuncional', 'especial'),
            $fila('salon_usos_multiples', 'Salón de usos múltiples', 'especial'),
            $fila('auditorio', 'Auditorio o Aula Magna', 'especial'),
            $fila('cocina', 'Cocina', 'especial'),
            $fila('comedor', 'Comedor', 'especial'),
            $fila('sala_artes', 'Sala de artes', 'especial'),
            $fila('otra_especial', 'Otra instalación especial', 'especial'),
        ];
    }
}
