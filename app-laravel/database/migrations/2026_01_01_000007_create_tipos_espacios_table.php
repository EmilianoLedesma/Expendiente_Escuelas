<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE tipos_espacios (
    id                  SERIAL PRIMARY KEY,
    clave               VARCHAR(60) NOT NULL UNIQUE,
    nombre              VARCHAR(150) NOT NULL,
    categoria           VARCHAR(30) NOT NULL
                            CHECK (categoria IN (\'administrativo\', \'cubiculo\', \'recreativo_deportivo\', \'especial\')),
    permite_campo_futbol    BOOLEAN NOT NULL DEFAULT false,
    permite_material_biblioteca BOOLEAN NOT NULL DEFAULT false
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS tipos_espacios CASCADE');
    }
};
