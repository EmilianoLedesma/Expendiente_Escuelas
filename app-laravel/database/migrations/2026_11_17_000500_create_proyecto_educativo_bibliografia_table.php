<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 20 del Proyecto Educativo (media superior): "Bibliografía del Proyecto". Una fila por referencia (de 1 a 50 por
 * nivel), en orden alfabético por autor (APA): autores, año de cuatro dígitos, título, fuente (editorial, revista o sitio) y
 * URL o DOI opcional (http o https). El guardado reemplaza toda la lista de un golpe, así que `orden` (1 a 50) es único por
 * nivel. El formato APA no se guarda: se arma al mostrar (FormatoReferenciaApa).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_bibliografia (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    orden             SMALLINT NOT NULL,
    autores           VARCHAR(300) NOT NULL,
    anio              SMALLINT NOT NULL,
    titulo            VARCHAR(300) NOT NULL,
    fuente            VARCHAR(200) NOT NULL,
    url               VARCHAR(500) NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_bibliografia_orden_unico UNIQUE (escuela_nivel_id, orden),
    CONSTRAINT pe_bibliografia_orden_valido CHECK (orden BETWEEN 1 AND 50),
    CONSTRAINT pe_bibliografia_anio_valido CHECK (anio BETWEEN 1000 AND 9999),
    CONSTRAINT pe_bibliografia_autores_no_vacio CHECK (btrim(autores) <> ''),
    CONSTRAINT pe_bibliografia_titulo_no_vacio CHECK (btrim(titulo) <> ''),
    CONSTRAINT pe_bibliografia_fuente_no_vacia CHECK (btrim(fuente) <> ''),
    CONSTRAINT pe_bibliografia_url_valida CHECK (url IS NULL OR url ~* '^https?://[^[:space:]]+$')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_bibliografia');
    }
};
