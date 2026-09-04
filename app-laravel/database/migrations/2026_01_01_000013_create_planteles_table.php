<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE planteles (
    id                      BIGSERIAL PRIMARY KEY,
    calle                   VARCHAR(150) NOT NULL,
    numero_ext              VARCHAR(20),
    numero_int              VARCHAR(20),
    colonia                 VARCHAR(150) NOT NULL,
    localidad               VARCHAR(150),
    municipio               VARCHAR(150) NOT NULL,
    codigo_postal           VARCHAR(10) NOT NULL,
    telefono                VARCHAR(20),
    correo_electronico      VARCHAR(150),
    metros_totales          NUMERIC(10,2),
    metros_construidos      NUMERIC(10,2),
    colindancia_norte       VARCHAR(150),
    colindancia_sur         VARCHAR(150),
    colindancia_este        VARCHAR(150),
    colindancia_oeste       VARCHAR(150),
    latitud                 NUMERIC(10,7),
    longitud                NUMERIC(10,7),
    area_civica_m2          NUMERIC(10,2),
    tiene_asta_bandera      BOOLEAN,
    created_at              TIMESTAMP NOT NULL DEFAULT now(),
    updated_at              TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS planteles CASCADE');
    }
};
