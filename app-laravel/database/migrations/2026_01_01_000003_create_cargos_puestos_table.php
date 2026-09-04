<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE cargos_puestos (
    id                  SERIAL PRIMARY KEY,
    nivel_educativo_id  SMALLINT NOT NULL REFERENCES niveles_educativos(id),
    nombre              VARCHAR(100) NOT NULL,   -- "Director Técnico", "Docente Titular de Grupo", "Responsable de Sala", "Asistente Educativo"
    requiere_asignatura BOOLEAN NOT NULL DEFAULT false,  -- true solo para Secundaria (docente por asignatura)
    requiere_sala       BOOLEAN NOT NULL DEFAULT false,  -- true solo para Inicial (responsable/asistente por sala)
    UNIQUE (nivel_educativo_id, nombre)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS cargos_puestos CASCADE');
    }
};
