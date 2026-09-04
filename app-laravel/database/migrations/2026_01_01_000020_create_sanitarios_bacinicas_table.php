<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE sanitarios_bacinicas (
    sanitario_id        BIGINT PRIMARY KEY REFERENCES sanitarios(id) ON DELETE CASCADE,
    cantidad_bacinicas  SMALLINT NOT NULL
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS sanitarios_bacinicas CASCADE');
    }
};
