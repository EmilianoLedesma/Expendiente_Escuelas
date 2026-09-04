<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AsignaturasSeeder extends Seeder
{
    /**
     * Asignaturas de Secundaria — COMPENDIO_MAESTRO §5.1, "Reglas de personal — Secundaria".
     */
    public function run(): void
    {
        DB::table('asignaturas')->insertOrIgnore([
            ['nombre' => 'Biología'],
            ['nombre' => 'Español'],
            ['nombre' => 'Física'],
            ['nombre' => 'Formación Cívica y Ética'],
            ['nombre' => 'Geografía'],
            ['nombre' => 'Historia'],
            ['nombre' => 'Inglés'],
            ['nombre' => 'Matemáticas'],
            ['nombre' => 'Química'],
            ['nombre' => 'Artes Visuales'],
            ['nombre' => 'Danza'],
            ['nombre' => 'Música'],
            ['nombre' => 'Teatro'],
            ['nombre' => 'Informática'],
            ['nombre' => 'Educación Física'],
            ['nombre' => 'Asignaturas Extracurriculares'],
        ]);
    }
}
