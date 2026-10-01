<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Typed data captured with an upload, read by the documental validation
 * engine (ADR-007). Not in DDL v3: four new extension tables, same shape as
 * constancias_seguridad_estructural — one row per document, keyed by the
 * document row, overwritten when the file is replaced. They touch none of
 * the existing tables. Raw DDL to keep the style of the 2026_01_01_*
 * migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE credenciales_ine (
    documento_escuela_id    BIGINT PRIMARY KEY REFERENCES documentos_escuela(id) ON DELETE CASCADE,
    nombre                  VARCHAR(200) NOT NULL,
    curp                    VARCHAR(18) NOT NULL
);');

        DB::unprepared('CREATE TABLE constancias_curp (
    documento_escuela_id    BIGINT PRIMARY KEY REFERENCES documentos_escuela(id) ON DELETE CASCADE,
    nombre                  VARCHAR(200) NOT NULL,
    curp                    VARCHAR(18) NOT NULL
);');

        DB::unprepared('CREATE TABLE constancias_situacion_fiscal (
    documento_escuela_id    BIGINT PRIMARY KEY REFERENCES documentos_escuela(id) ON DELETE CASCADE,
    nombre_razon_social     VARCHAR(200) NOT NULL,
    rfc                     VARCHAR(13) NOT NULL
);');

        // Column sizes mirror planteles, the declared side of the comparison.
        DB::unprepared('CREATE TABLE certificados_numero_oficial (
    documento_plantel_id    BIGINT PRIMARY KEY REFERENCES documentos_plantel(id) ON DELETE CASCADE,
    calle                   VARCHAR(150) NOT NULL,
    numero_ext              VARCHAR(20),
    colonia                 VARCHAR(150) NOT NULL,
    municipio               VARCHAR(150) NOT NULL,
    codigo_postal           VARCHAR(10) NOT NULL
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS certificados_numero_oficial CASCADE');
        DB::statement('DROP TABLE IF EXISTS constancias_situacion_fiscal CASCADE');
        DB::statement('DROP TABLE IF EXISTS constancias_curp CASCADE');
        DB::statement('DROP TABLE IF EXISTS credenciales_ine CASCADE');
    }
};
