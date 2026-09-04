<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CargosPuestosSeeder extends Seeder
{
    /**
     * Cargos/puestos por nivel — COMPENDIO_MAESTRO §5 (Inicial) y §5.1
     * (Preescolar/Primaria/Secundaria).
     */
    public function run(): void
    {
        $niveles = DB::table('niveles_educativos')->pluck('id', 'clave');

        DB::table('cargos_puestos')->insertOrIgnore([
            // Inicial
            ['nivel_educativo_id' => $niveles['inicial'], 'nombre' => 'Director Técnico', 'requiere_asignatura' => false, 'requiere_sala' => false],
            ['nivel_educativo_id' => $niveles['inicial'], 'nombre' => 'Responsable de Sala', 'requiere_asignatura' => false, 'requiere_sala' => true],
            ['nivel_educativo_id' => $niveles['inicial'], 'nombre' => 'Asistente Educativo', 'requiere_asignatura' => false, 'requiere_sala' => true],
            ['nivel_educativo_id' => $niveles['inicial'], 'nombre' => 'Responsable de Filtro y Fomento a la Salud', 'requiere_asignatura' => false, 'requiere_sala' => false],
            // Preescolar
            ['nivel_educativo_id' => $niveles['preescolar'], 'nombre' => 'Director Técnico', 'requiere_asignatura' => false, 'requiere_sala' => false],
            ['nivel_educativo_id' => $niveles['preescolar'], 'nombre' => 'Docente Titular de Grupo', 'requiere_asignatura' => false, 'requiere_sala' => false],
            ['nivel_educativo_id' => $niveles['preescolar'], 'nombre' => 'Asistente de Grupo', 'requiere_asignatura' => false, 'requiere_sala' => false],
            ['nivel_educativo_id' => $niveles['preescolar'], 'nombre' => 'Docente de Educación Física', 'requiere_asignatura' => false, 'requiere_sala' => false],
            ['nivel_educativo_id' => $niveles['preescolar'], 'nombre' => 'Docente de Inglés', 'requiere_asignatura' => false, 'requiere_sala' => false],
            // Primaria
            ['nivel_educativo_id' => $niveles['primaria'], 'nombre' => 'Director Técnico', 'requiere_asignatura' => false, 'requiere_sala' => false],
            ['nivel_educativo_id' => $niveles['primaria'], 'nombre' => 'Docente Titular de Grupo', 'requiere_asignatura' => false, 'requiere_sala' => false],
            ['nivel_educativo_id' => $niveles['primaria'], 'nombre' => 'Docente de Educación Física', 'requiere_asignatura' => false, 'requiere_sala' => false],
            ['nivel_educativo_id' => $niveles['primaria'], 'nombre' => 'Docente de Inglés', 'requiere_asignatura' => false, 'requiere_sala' => false],
            ['nivel_educativo_id' => $niveles['primaria'], 'nombre' => 'Docente de Computación', 'requiere_asignatura' => false, 'requiere_sala' => false],
            // Secundaria
            ['nivel_educativo_id' => $niveles['secundaria'], 'nombre' => 'Director Técnico', 'requiere_asignatura' => false, 'requiere_sala' => false],
            ['nivel_educativo_id' => $niveles['secundaria'], 'nombre' => 'Docente Titular', 'requiere_asignatura' => true, 'requiere_sala' => false],
        ]);
    }
}
