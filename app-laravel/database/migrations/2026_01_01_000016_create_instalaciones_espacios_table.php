<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE instalaciones_espacios (
    id                  BIGSERIAL PRIMARY KEY,
    plantel_id          BIGINT NOT NULL REFERENCES planteles(id) ON DELETE CASCADE,
    tipo_espacio_id     INTEGER NOT NULL REFERENCES tipos_espacios(id),
    cantidad            SMALLINT,
    superficie_m2       NUMERIC(10,2),
    capacidad_promedio  SMALLINT,
    ventilacion_natural BOOLEAN,
    iluminacion_natural BOOLEAN,
    destinado_a         VARCHAR(200),
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS instalaciones_espacios CASCADE');
    }
};
