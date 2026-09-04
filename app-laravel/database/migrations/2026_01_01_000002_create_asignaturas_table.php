<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE asignaturas (
    id      SERIAL PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL UNIQUE
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS asignaturas CASCADE');
    }
};
