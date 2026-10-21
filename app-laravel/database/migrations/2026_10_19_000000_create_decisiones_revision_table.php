<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Decisión vigente del revisor sobre cada paso de un nivel (aceptado / rechazado con motivo).
 * Revisión por el revisor, proyecto 2 (spec 2026-10-19-revision-revisor). No está en
 * docs/ddl_sistema_incorporacion_v3.sql: tabla nueva aprobada por el dueño.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE decisiones_revision (
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    paso_clave        VARCHAR(30) NOT NULL CHECK (paso_clave IN ('plantel','responsable','documentos','niveles','documentos_nivel','inmueble','infraestructura','mobiliario','plan_estudios','plantilla_docente','matricula')),
    estado            VARCHAR(10) NOT NULL CHECK (estado IN ('aceptado','rechazado')),
    motivo            TEXT,
    decidido_por      BIGINT NOT NULL REFERENCES users(id),
    decidido_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (escuela_nivel_id, paso_clave),
    CHECK (estado = 'aceptado' OR (motivo IS NOT NULL AND length(btrim(motivo)) > 0))
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS decisiones_revision CASCADE');
    }
};
