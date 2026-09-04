<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE estados_expediente (
    id      SMALLSERIAL PRIMARY KEY,
    clave   VARCHAR(30) NOT NULL UNIQUE,
    nombre  VARCHAR(100) NOT NULL,
    orden   SMALLINT NOT NULL
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS estados_expediente CASCADE');
    }
};
