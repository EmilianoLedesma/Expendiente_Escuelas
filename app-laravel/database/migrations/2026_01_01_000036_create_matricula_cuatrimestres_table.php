<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE matricula_cuatrimestres (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    numero_cuatrimestre SMALLINT NOT NULL CHECK (numero_cuatrimestre BETWEEN 1 AND 6),
    cantidad_alumnos    SMALLINT NOT NULL DEFAULT 0,
    UNIQUE (escuela_nivel_id, numero_cuatrimestre)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS matricula_cuatrimestres CASCADE');
    }
};
