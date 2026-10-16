<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Prioridad que el supervisor fija a cada nivel; sin fila = media. Fuera de escuela_niveles para no tocar datos del trámite.
 * HUD SEDEQ, proyecto 1 (spec 2026-10-16-hud-sedeq-roles-asignacion). No está en
 * docs/ddl_sistema_incorporacion_v3.sql: tabla nueva aprobada por el dueño (2026-10-16).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE prioridades_revision (
    escuela_nivel_id  BIGINT PRIMARY KEY REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    prioridad         VARCHAR(5) NOT NULL DEFAULT 'media' CHECK (prioridad IN ('alta','media','baja')),
    actualizado_por   BIGINT REFERENCES users(id),
    updated_at        TIMESTAMP NOT NULL DEFAULT now()
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS prioridades_revision CASCADE');
    }
};
