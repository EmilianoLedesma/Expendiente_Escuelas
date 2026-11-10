<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 2 del Proyecto Educativo (media superior): "Filosofía Institucional". Una fila por nivel; todo NULL porque
 * la sección se guarda como borrador. Los rangos de palabras los valida ReglasFilosofiaProyecto, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_filosofia (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    mision            TEXT NULL,
    vision            TEXT NULL,
    valores           TEXT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_filosofia_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_filosofia_mision_no_vacia CHECK (mision IS NULL OR btrim(mision) <> ''),
    CONSTRAINT pe_filosofia_vision_no_vacia CHECK (vision IS NULL OR btrim(vision) <> ''),
    CONSTRAINT pe_filosofia_valores_no_vacios CHECK (valores IS NULL OR btrim(valores) <> '')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_filosofia');
    }
};
