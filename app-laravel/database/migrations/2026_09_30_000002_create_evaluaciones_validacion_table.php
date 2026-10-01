<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One row per run of the final documental validation (ADR-007, owner
 * decision: every report is kept as a formatted PDF). Not in DDL v3: a new
 * table, touches none of the existing ones. `resultados` keeps what the
 * applicant was shown, so the page and SEDEQ can re-read it without
 * re-running the engine on data that may have changed since.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE evaluaciones_validacion (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_id          BIGINT NOT NULL REFERENCES escuelas(id) ON DELETE CASCADE,
    archivo_path        VARCHAR(500) NOT NULL,
    lista_para_envio    BOOLEAN NOT NULL,
    resultados          JSONB NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX evaluaciones_validacion_escuela_id_index ON evaluaciones_validacion (escuela_id);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS evaluaciones_validacion CASCADE');
    }
};
