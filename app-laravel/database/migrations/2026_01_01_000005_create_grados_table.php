<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE grados (
    id                  SERIAL PRIMARY KEY,
    nivel_educativo_id  SMALLINT NOT NULL REFERENCES niveles_educativos(id),
    nombre              VARCHAR(20) NOT NULL,   -- "1°", "2°", etc.
    orden               SMALLINT NOT NULL,
    UNIQUE (nivel_educativo_id, orden)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS grados CASCADE');
    }
};
