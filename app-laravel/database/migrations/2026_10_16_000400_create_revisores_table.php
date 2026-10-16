<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una fila por persona invitada alguna vez como revisor; revocado_at IS NOT NULL es la única definición de revisor revocado.
 * HUD SEDEQ, proyecto 1 (spec 2026-10-16-hud-sedeq-roles-asignacion). No está en
 * docs/ddl_sistema_incorporacion_v3.sql: tabla nueva aprobada por el dueño (2026-10-16).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE revisores (
    user_id      BIGINT PRIMARY KEY REFERENCES users(id),
    revocado_at  TIMESTAMP NULL
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS revisores CASCADE');
    }
};
