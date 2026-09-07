<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PasosCapturaSeeder extends Seeder
{
    /**
     * Los seis sub-pasos del Paso 3 (PRD §5), en el orden fijo del wizard.
     */
    public function run(): void
    {
        DB::table('pasos_captura')->insertOrIgnore([
            ['clave' => 'inmueble', 'nombre' => 'Datos del inmueble', 'orden' => 1],
            ['clave' => 'infraestructura', 'nombre' => 'Infraestructura del nivel', 'orden' => 2],
            ['clave' => 'mobiliario', 'nombre' => 'Mobiliario', 'orden' => 3],
            ['clave' => 'plan_estudios', 'nombre' => 'Plan de estudios y modalidad', 'orden' => 4],
            ['clave' => 'plantilla_docente', 'nombre' => 'Plantilla docente', 'orden' => 5],
            ['clave' => 'matricula', 'nombre' => 'Matrícula', 'orden' => 6],
        ]);
    }
}
