<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE mobiliario_conceptos (
    id              SERIAL PRIMARY KEY,
    sala_id         SMALLINT REFERENCES salas(id),  -- NULL si el concepto no depende de una sala específica
    nombre          VARCHAR(150) NOT NULL,           -- "Cuna con barandal", "Mesa infantil"
    tipo_ratio      VARCHAR(20) NOT NULL
                        CHECK (tipo_ratio IN (\'fijo_por_sala\', \'por_alumno_ratio\')),
    valor_ratio     NUMERIC(6,2) NOT NULL,           -- 1 (fijo) o el divisor del ratio (ej. 2 = "1 por cada 2 niños")
    fuente          VARCHAR(200)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS mobiliario_conceptos CASCADE');
    }
};
