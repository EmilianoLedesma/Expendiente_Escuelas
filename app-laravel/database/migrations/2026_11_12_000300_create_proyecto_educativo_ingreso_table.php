<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 8 del Proyecto Educativo (media superior): "Perfil de Ingreso". Una fila por nivel; los cuatro textos son NULL
 * hasta que se guardan (todo o nada). Los rangos de palabras los valida ReglasIngresoProyecto, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_ingreso (
    id                       BIGSERIAL PRIMARY KEY,
    escuela_nivel_id         BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    perfil_ingreso           TEXT NULL,
    mecanismo_ingreso        TEXT NULL,
    costo_usuario            TEXT NULL,
    instrumentos_evaluacion  TEXT NULL,
    created_at               TIMESTAMP NOT NULL DEFAULT now(),
    updated_at               TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_ingreso_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_ingreso_perfil_no_vacio CHECK (perfil_ingreso IS NULL OR btrim(perfil_ingreso) <> ''),
    CONSTRAINT pe_ingreso_mecanismo_no_vacio CHECK (mecanismo_ingreso IS NULL OR btrim(mecanismo_ingreso) <> ''),
    CONSTRAINT pe_ingreso_costo_no_vacio CHECK (costo_usuario IS NULL OR btrim(costo_usuario) <> ''),
    CONSTRAINT pe_ingreso_instrumentos_no_vacio CHECK (instrumentos_evaluacion IS NULL OR btrim(instrumentos_evaluacion) <> '')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_ingreso');
    }
};
