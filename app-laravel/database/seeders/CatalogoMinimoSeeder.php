<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoMinimoSeeder extends Seeder
{
    /**
     * Seeds mínimos de catálogo — ddl_sistema_incorporacion_v3.sql lines 563-596.
     */
    public function run(): void
    {
        DB::table('niveles_educativos')->insertOrIgnore([
            ['clave' => 'inicial', 'nombre' => 'Educación Inicial', 'orden' => 1],
            ['clave' => 'preescolar', 'nombre' => 'Preescolar', 'orden' => 2],
            ['clave' => 'primaria', 'nombre' => 'Primaria', 'orden' => 3],
            ['clave' => 'secundaria', 'nombre' => 'Secundaria', 'orden' => 4],
            ['clave' => 'media_superior', 'nombre' => 'Media Superior', 'orden' => 5],
            ['clave' => 'superior', 'nombre' => 'Superior', 'orden' => 6],
            ['clave' => 'posgrado', 'nombre' => 'Posgrado', 'orden' => 7],
        ]);

        DB::table('estados_expediente')->insertOrIgnore([
            ['clave' => 'preregistro', 'nombre' => 'Preregistro', 'orden' => 1],
            ['clave' => 'en_captura', 'nombre' => 'En captura', 'orden' => 2],
            ['clave' => 'en_revision', 'nombre' => 'En revisión', 'orden' => 3],
            ['clave' => 'con_observaciones', 'nombre' => 'Con observaciones', 'orden' => 4],
            ['clave' => 'aprobado', 'nombre' => 'Aprobado', 'orden' => 5],
            ['clave' => 'rechazado', 'nombre' => 'Rechazado', 'orden' => 6],
        ]);

        DB::table('salas')->insertOrIgnore([
            ['clave' => 'lactantes_a', 'nombre' => 'Lactantes A', 'edad_min_meses' => 1, 'edad_max_meses' => 6, 'orden' => 1],
            ['clave' => 'lactantes_b', 'nombre' => 'Lactantes B', 'edad_min_meses' => 7, 'edad_max_meses' => 12, 'orden' => 2],
            ['clave' => 'lactantes_c', 'nombre' => 'Lactantes C', 'edad_min_meses' => 13, 'edad_max_meses' => 18, 'orden' => 3],
            ['clave' => 'maternal_a', 'nombre' => 'Maternal A', 'edad_min_meses' => 19, 'edad_max_meses' => 24, 'orden' => 4],
            ['clave' => 'maternal_b', 'nombre' => 'Maternal B', 'edad_min_meses' => 25, 'edad_max_meses' => 35, 'orden' => 5],
        ]);

        DB::table('tipos_material_biblioteca')->insertOrIgnore([
            ['clave' => 'libros', 'nombre' => 'Libros'],
            ['clave' => 'periodicos', 'nombre' => 'Periódicos'],
            ['clave' => 'revistas_especializadas', 'nombre' => 'Revistas especializadas'],
            ['clave' => 'diapositivas', 'nombre' => 'Diapositivas'],
            ['clave' => 'videos', 'nombre' => 'Videos'],
            ['clave' => 'peliculas', 'nombre' => 'Películas'],
            ['clave' => 'discos_compactos', 'nombre' => 'Discos compactos'],
            ['clave' => 'software', 'nombre' => 'Software'],
            ['clave' => 'otro', 'nombre' => 'Otro'],
        ]);
    }
}
