<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE salas (
    id              SMALLSERIAL PRIMARY KEY,
    clave           VARCHAR(20) NOT NULL UNIQUE,   -- lactantes_a, lactantes_b, lactantes_c, maternal_a, maternal_b
    nombre          VARCHAR(50) NOT NULL,           -- "Lactantes A"
    edad_min_meses  SMALLINT NOT NULL,
    edad_max_meses  SMALLINT NOT NULL,
    orden           SMALLINT NOT NULL
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS salas CASCADE');
    }
};
