<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 14 del Proyecto Educativo (media superior): "Plan de Estudios", bloque "Componente y Enfoque de Formación
 * Laboral". Una fila por nivel, creada al guardar (todo o nada: las dos primeras formaciones nunca son NULL). "Otras
 * formaciones" es opcional (NULL si se deja vacía). Ningún texto guardado puede estar en blanco; el rango de palabras lo
 * valida ReglasFormacionLaboralProyecto, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_formacion_laboral (
    id                    BIGSERIAL PRIMARY KEY,
    escuela_nivel_id      BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    formacion_laboral_1   TEXT NOT NULL,
    formacion_laboral_2   TEXT NOT NULL,
    otras_formaciones     TEXT NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT now(),
    updated_at            TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_formacion_laboral_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_formacion_laboral_1_no_vacio CHECK (btrim(formacion_laboral_1) <> ''),
    CONSTRAINT pe_formacion_laboral_2_no_vacio CHECK (btrim(formacion_laboral_2) <> ''),
    CONSTRAINT pe_formacion_laboral_otras_no_vacio CHECK (otras_formaciones IS NULL OR btrim(otras_formaciones) <> '')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_formacion_laboral');
    }
};
