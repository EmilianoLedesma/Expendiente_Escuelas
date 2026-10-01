<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner decision (ADR-007, P8): for fisica_con_gestor the uploaded INE and
 * CURP are the gestor's, so the gestor's CURP must be declared to compare
 * against them. Nullable: existing gestores rows predate it. Only change to
 * a DDL v3 table in this work.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE gestores ADD COLUMN curp VARCHAR(18)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE gestores DROP COLUMN curp');
    }
};
