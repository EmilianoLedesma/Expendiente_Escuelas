<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Avance de revisión por nivel (secciones revisadas). Independiente de la asignación: desasignar nunca lo borra.
 * HUD SEDEQ, proyecto 1 (spec 2026-10-16-hud-sedeq-roles-asignacion). No está en
 * docs/ddl_sistema_incorporacion_v3.sql: tabla nueva aprobada por el dueño (2026-10-16).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE progreso_revision (
    escuela_nivel_id     BIGINT PRIMARY KEY REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    secciones_revisadas  SMALLINT NOT NULL DEFAULT 0 CHECK (secciones_revisadas >= 0),
    secciones_total      SMALLINT NOT NULL DEFAULT 0,
    CHECK (secciones_total >= 0 AND secciones_revisadas <= secciones_total)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS progreso_revision CASCADE');
    }
};
