<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE escuela_nivel_pasos (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    paso_captura_id   SMALLINT NOT NULL REFERENCES pasos_captura(id),
    estado            VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                          CHECK (estado IN ('pendiente','en_progreso','completado')),
    completado_at     TIMESTAMP,
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE (escuela_nivel_id, paso_captura_id)
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS escuela_nivel_pasos CASCADE');
    }
};
