<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE reglas_validacion (
    id                  SERIAL PRIMARY KEY,
    nivel_educativo_id  SMALLINT NOT NULL REFERENCES niveles_educativos(id),
    tipo_regla          VARCHAR(30) NOT NULL
                            CHECK (tipo_regla IN (\'superficie\', \'personal\', \'mobiliario\')),
    concepto            VARCHAR(150) NOT NULL,
    condicion_min       INTEGER,
    condicion_max       INTEGER,
    valor_numerico      NUMERIC(10,2),
    unidad              VARCHAR(20),
    fuente              VARCHAR(200),
    created_at          TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS reglas_validacion CASCADE');
    }
};
