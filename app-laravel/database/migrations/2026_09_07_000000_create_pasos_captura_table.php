<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE pasos_captura (
    id      SMALLSERIAL PRIMARY KEY,
    clave   VARCHAR(40) NOT NULL UNIQUE,
    nombre  VARCHAR(100) NOT NULL,
    orden   SMALLINT NOT NULL
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS pasos_captura CASCADE');
    }
};
