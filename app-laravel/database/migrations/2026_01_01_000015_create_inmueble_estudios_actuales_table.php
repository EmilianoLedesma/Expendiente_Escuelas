<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE inmueble_estudios_actuales (
    id                  BIGSERIAL PRIMARY KEY,
    plantel_id          BIGINT NOT NULL REFERENCES planteles(id) ON DELETE CASCADE,
    nivel_educativo_id  SMALLINT REFERENCES niveles_educativos(id),  -- NULL si es "otro"
    otro_nivel_texto    VARCHAR(150),
    numero_alumnos      SMALLINT NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS inmueble_estudios_actuales CASCADE');
    }
};
