<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE escuelas (
    id              BIGSERIAL PRIMARY KEY,
    plantel_id      BIGINT NOT NULL REFERENCES planteles(id),
    nombre_aprobado VARCHAR(200),
    created_at      TIMESTAMP NOT NULL DEFAULT now(),
    updated_at      TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS escuelas CASCADE');
    }
};
