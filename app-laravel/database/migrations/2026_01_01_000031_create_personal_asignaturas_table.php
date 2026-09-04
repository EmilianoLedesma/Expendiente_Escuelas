<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE personal_asignaturas (
    personal_id     BIGINT PRIMARY KEY REFERENCES personal(id) ON DELETE CASCADE,
    asignatura_id   INTEGER NOT NULL REFERENCES asignaturas(id)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS personal_asignaturas CASCADE');
    }
};
