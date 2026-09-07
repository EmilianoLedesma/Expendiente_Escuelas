<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE reglas_validacion (
    id                  SERIAL PRIMARY KEY,
    clave               VARCHAR(100) NOT NULL UNIQUE,
    nivel_educativo_id  SMALLINT NOT NULL REFERENCES niveles_educativos(id),
    tipo_regla          VARCHAR(30) NOT NULL
                            CHECK (tipo_regla IN (\'superficie\', \'personal\', \'mobiliario\', \'infraestructura\')),
    tipo_calculo        VARCHAR(30) NOT NULL
                            CHECK (tipo_calculo IN (
                                \'ratio_por_alumno\', \'ratio_por_grado\', \'minimo_fijo\',
                                \'adicional_fijo\', \'factor\', \'personal_obligatorio\',
                                \'personal_umbral\', \'personal_proporcional\', \'personal_por_espacio\'
                            )),
    ambito              VARCHAR(20) NOT NULL
                            CHECK (ambito IN (\'aula\', \'sala\', \'plantel\', \'escuela\', \'predio\')),
    redondeo            VARCHAR(10) NOT NULL
                            CHECK (redondeo IN (\'arriba\', \'abajo\', \'na\')),
    concepto            TEXT NOT NULL,
    cargo_puesto_id     INTEGER REFERENCES cargos_puestos(id),
    condicion_min       NUMERIC(10,2),
    condicion_max       NUMERIC(10,2),
    valor_numerico      NUMERIC(10,2) NOT NULL,
    unidad              VARCHAR(30) NOT NULL,
    fuente              TEXT NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS reglas_validacion CASCADE');
    }
};
