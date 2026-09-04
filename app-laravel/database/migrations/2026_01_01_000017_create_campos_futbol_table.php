<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE campos_futbol (
    instalacion_espacio_id  BIGINT PRIMARY KEY REFERENCES instalaciones_espacios(id) ON DELETE CASCADE,
    tipo_superficie         VARCHAR(50),
    formato                 VARCHAR(20)  -- 11, 7, 5, baby_fut
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS campos_futbol CASCADE');
    }
};
