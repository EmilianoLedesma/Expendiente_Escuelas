<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE niveles_tipos_espacios (
    nivel_educativo_id  SMALLINT NOT NULL REFERENCES niveles_educativos(id),
    tipo_espacio_id     INTEGER NOT NULL REFERENCES tipos_espacios(id),
    obligatorio         BOOLEAN NOT NULL DEFAULT false,
    PRIMARY KEY (nivel_educativo_id, tipo_espacio_id)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS niveles_tipos_espacios CASCADE');
    }
};
