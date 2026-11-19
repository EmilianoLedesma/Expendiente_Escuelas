<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Observaciones del acta de una visita (lista ordenada de texto libre). */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE observaciones_visita (
    id         BIGSERIAL PRIMARY KEY,
    visita_id  BIGINT NOT NULL REFERENCES visitas_inspeccion(id) ON DELETE CASCADE,
    orden      SMALLINT NOT NULL,
    texto      VARCHAR(1000) NOT NULL,
    CONSTRAINT observaciones_visita_orden_unico UNIQUE (visita_id, orden),
    CONSTRAINT observaciones_visita_orden_valido CHECK (orden >= 1),
    CONSTRAINT observaciones_visita_texto_no_vacio CHECK (btrim(texto) <> '')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS observaciones_visita CASCADE');
    }
};
