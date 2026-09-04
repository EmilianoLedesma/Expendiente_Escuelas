<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE matricula_semestres (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    numero_semestre     SMALLINT NOT NULL CHECK (numero_semestre BETWEEN 1 AND 6),
    modalidad           VARCHAR(20) NOT NULL
                            CHECK (modalidad IN (\'escolarizado\', \'no_escolarizado\', \'mixto\')),
    cantidad_alumnos    SMALLINT NOT NULL DEFAULT 0,
    UNIQUE (escuela_nivel_id, numero_semestre, modalidad)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS matricula_semestres CASCADE');
    }
};
