<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE tipos_documentos (
    id                  SERIAL PRIMARY KEY,
    clave               VARCHAR(60) NOT NULL UNIQUE,
    nombre              VARCHAR(150) NOT NULL,
    aplica_persona      VARCHAR(20) NOT NULL DEFAULT \'ambas\'
                            CHECK (aplica_persona IN (\'fisica\', \'moral\', \'ambas\')),
    nivel_educativo_id  SMALLINT REFERENCES niveles_educativos(id),
    requiere_pdf        BOOLEAN NOT NULL DEFAULT true,
    vigencia_max_dias   INTEGER,
    ambito              VARCHAR(20) NOT NULL
                            CHECK (ambito IN (\'plantel\', \'escuela\', \'escuela_nivel\')),
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS tipos_documentos CASCADE');
    }
};
