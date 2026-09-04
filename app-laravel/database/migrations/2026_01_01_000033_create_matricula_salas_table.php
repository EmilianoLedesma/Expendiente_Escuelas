<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE matricula_salas (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    sala_id             SMALLINT NOT NULL REFERENCES salas(id),
    cantidad_alumnos    SMALLINT NOT NULL DEFAULT 0,
    UNIQUE (escuela_nivel_id, sala_id)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS matricula_salas CASCADE');
    }
};
