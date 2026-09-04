<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE personal_salas (
    personal_id BIGINT PRIMARY KEY REFERENCES personal(id) ON DELETE CASCADE,
    sala_id     SMALLINT NOT NULL REFERENCES salas(id)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS personal_salas CASCADE');
    }
};
