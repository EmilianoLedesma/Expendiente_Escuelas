<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE niveles_educativos (
    id              SMALLSERIAL PRIMARY KEY,
    clave           VARCHAR(30) NOT NULL UNIQUE,
    nombre          VARCHAR(100) NOT NULL,
    orden           SMALLINT NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT now(),
    updated_at      TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS niveles_educativos CASCADE');
    }
};
