<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE tipos_material_biblioteca (
    id      SMALLSERIAL PRIMARY KEY,
    clave   VARCHAR(30) NOT NULL UNIQUE,  -- libros, periodicos, revistas_especializadas, diapositivas, videos, peliculas, discos_compactos, software, otro
    nombre  VARCHAR(100) NOT NULL
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS tipos_material_biblioteca CASCADE');
    }
};
