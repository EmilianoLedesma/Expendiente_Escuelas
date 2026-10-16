<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revisor asignado a cada escuela_nivel en revisión (una fila por nivel).
 * HUD SEDEQ, proyecto 1 (spec 2026-10-16-hud-sedeq-roles-asignacion). No está en
 * docs/ddl_sistema_incorporacion_v3.sql: tabla nueva aprobada por el dueño (2026-10-16).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE asignaciones_revision (
    escuela_nivel_id  BIGINT PRIMARY KEY REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    revisor_id        BIGINT NOT NULL REFERENCES users(id),
    asignado_por      BIGINT NOT NULL REFERENCES users(id),
    asignado_at       TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX asignaciones_revision_revisor_id_index ON asignaciones_revision(revisor_id);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS asignaciones_revision CASCADE');
    }
};
