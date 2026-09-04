<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE matricula_grados (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    grado_id            INTEGER NOT NULL REFERENCES grados(id),
    grupo               VARCHAR(5) NOT NULL DEFAULT \'A\',  -- etiqueta libre (A, B, C... K); no requiere catálogo normativo
    cantidad_alumnos    SMALLINT NOT NULL DEFAULT 0,
    UNIQUE (escuela_nivel_id, grado_id, grupo)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS matricula_grados CASCADE');
    }
};
