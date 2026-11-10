<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 3 del Proyecto Educativo (media superior): "Objetivos Institucionales". Una fila por nivel; todo NULL porque
 * la sección se guarda como borrador. Los rangos de palabras los valida ReglasObjetivosProyecto, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_objetivos (
    id                     BIGSERIAL PRIMARY KEY,
    escuela_nivel_id       BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    objetivo_general       TEXT NULL,
    objetivos_especificos  TEXT NULL,
    created_at             TIMESTAMP NOT NULL DEFAULT now(),
    updated_at             TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_objetivos_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_objetivos_general_no_vacio CHECK (objetivo_general IS NULL OR btrim(objetivo_general) <> ''),
    CONSTRAINT pe_objetivos_especificos_no_vacios CHECK (objetivos_especificos IS NULL OR btrim(objetivos_especificos) <> '')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_objetivos');
    }
};
