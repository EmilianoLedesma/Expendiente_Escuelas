<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE servicios_cercanos (
    id                  BIGSERIAL PRIMARY KEY,
    plantel_id          BIGINT NOT NULL REFERENCES planteles(id) ON DELETE CASCADE,
    nombre              VARCHAR(200) NOT NULL,
    tipo                VARCHAR(20) NOT NULL CHECK (tipo IN (\'salud\', \'emergencia\')),
    es_publico          BOOLEAN,
    distancia_valor     NUMERIC(6,2),
    distancia_unidad    VARCHAR(5) CHECK (distancia_unidad IN (\'m\', \'km\')),
    created_at          TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS servicios_cercanos CASCADE');
    }
};
