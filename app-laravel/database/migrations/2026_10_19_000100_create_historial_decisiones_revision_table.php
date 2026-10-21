<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Historial append-only de las decisiones del revisor: una fila por decisión tomada, aunque luego se reemplace.
 * Revisión por el revisor, proyecto 2 (spec 2026-10-19-revision-revisor). Tabla nueva, fuera del DDL v3.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE historial_decisiones_revision (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    paso_clave        VARCHAR(30) NOT NULL CHECK (paso_clave IN ('plantel','responsable','documentos','niveles','documentos_nivel','inmueble','infraestructura','mobiliario','plan_estudios','plantilla_docente','matricula')),
    estado            VARCHAR(10) NOT NULL CHECK (estado IN ('aceptado','rechazado')),
    motivo            TEXT,
    decidido_por      BIGINT NOT NULL REFERENCES users(id),
    decidido_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (estado = 'aceptado' OR (motivo IS NOT NULL AND length(btrim(motivo)) > 0))
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS historial_decisiones_revision CASCADE');
    }
};
