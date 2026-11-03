<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bitácora append-only de acciones administrativas (archivar/restaurar/purgar). Spec 2026-11-03-admin-fundacion.
 * Sin FK (actor y objeto pueden purgarse) y sin CHECK en accion (proyectos posteriores añaden valores).
 * detalle nunca guarda nombre ni correo del sujeto.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE bitacora_acciones (
    id          BIGSERIAL PRIMARY KEY,
    actor_id    BIGINT NOT NULL,
    accion      VARCHAR(40) NOT NULL,
    objeto_tipo VARCHAR(20) NOT NULL,
    objeto_id   BIGINT NOT NULL,
    motivo      TEXT NULL,
    detalle     JSONB NOT NULL DEFAULT '{}',
    created_at  TIMESTAMP NOT NULL DEFAULT now()
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS bitacora_acciones');
    }
};
