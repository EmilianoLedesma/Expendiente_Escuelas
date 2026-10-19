<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo geográfico nacional (entidades federativas y municipios) importado de
 * la API api_info_mexico por `php artisan geografia:importar` (ADR-017). Las
 * claves son las del catálogo de origen: `entidades_federativas.clave` es la
 * clave de entidad y `municipios.clave` la clave de municipio dentro de ella.
 * Tablas nuevas; no sustituyen datos existentes. Autorizado por el dueño del
 * proyecto (2026-10-19).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE entidades_federativas (
    id            BIGSERIAL PRIMARY KEY,
    clave         SMALLINT     NOT NULL UNIQUE,
    nombre        VARCHAR(100) NOT NULL,
    abreviatura   VARCHAR(10)
);');

        DB::unprepared('CREATE TABLE municipios (
    id                      BIGSERIAL PRIMARY KEY,
    entidad_federativa_id   BIGINT       NOT NULL REFERENCES entidades_federativas(id) ON DELETE CASCADE,
    clave                   SMALLINT     NOT NULL,
    nombre                  VARCHAR(150) NOT NULL,
    UNIQUE (entidad_federativa_id, clave)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS municipios');
        DB::statement('DROP TABLE IF EXISTS entidades_federativas');
    }
};
