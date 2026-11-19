<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Respuesta del solicitante a las observaciones de una visita (nota; los PDF van en respuestas_visita_archivos). */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE respuestas_visita (
    id          BIGSERIAL PRIMARY KEY,
    visita_id   BIGINT NOT NULL REFERENCES visitas_inspeccion(id) ON DELETE CASCADE,
    nota        VARCHAR(2000) NOT NULL,
    creada_por  BIGINT NOT NULL REFERENCES users(id),
    created_at  TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT respuestas_visita_nota_no_vacia CHECK (btrim(nota) <> '')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS respuestas_visita CASCADE');
    }
};
