<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Archivo (borrado lógico) de trámites y cuentas. Spec 2026-11-03-admin-fundacion.
 * archivado_por sin FK a propósito: purgar una cuenta no debe romper ni bloquearse por quien archivó.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['escuelas', 'users'] as $tabla) {
            DB::unprepared("ALTER TABLE {$tabla}
    ADD COLUMN archivado_at     TIMESTAMP NULL,
    ADD COLUMN archivado_por    BIGINT NULL,
    ADD COLUMN archivado_motivo TEXT NULL,
    ADD COLUMN archivado_lote   UUID NULL;");
            DB::unprepared("CREATE INDEX {$tabla}_archivado_idx ON {$tabla} (archivado_at) WHERE archivado_at IS NOT NULL;");
        }
    }

    public function down(): void
    {
        foreach (['escuelas', 'users'] as $tabla) {
            DB::statement("DROP INDEX IF EXISTS {$tabla}_archivado_idx");
            DB::statement("ALTER TABLE {$tabla} DROP COLUMN IF EXISTS archivado_at, DROP COLUMN IF EXISTS archivado_por, DROP COLUMN IF EXISTS archivado_motivo, DROP COLUMN IF EXISTS archivado_lote");
        }
    }
};
