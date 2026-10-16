<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bitácora de asignaciones: quién asignó, reasignó o desasignó (sobrecarga o revocación) y cuándo.
 * HUD SEDEQ, proyecto 1 (spec 2026-10-16-hud-sedeq-roles-asignacion). No está en
 * docs/ddl_sistema_incorporacion_v3.sql: tabla nueva aprobada por el dueño (2026-10-16).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE bitacora_asignaciones (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    revisor_id        BIGINT REFERENCES users(id),
    accion            VARCHAR(30) NOT NULL CHECK (accion IN ('asignar','reasignar','desasignar_sobrecarga','desasignar_revocacion')),
    actor_id          BIGINT NOT NULL REFERENCES users(id),
    created_at        TIMESTAMP NOT NULL DEFAULT now()
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS bitacora_asignaciones CASCADE');
    }
};
