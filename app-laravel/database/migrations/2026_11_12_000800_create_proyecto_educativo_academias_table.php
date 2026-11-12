<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 12 del Proyecto Educativo (media superior): "Programa de Academias Docentes". Una fila por nivel, creada al
 * guardar (todo o nada, así que el tipo y la justificación nunca son NULL). La descripción solo existe con Mixta u Otros.
 * El rango de palabras lo valida ReglasAcademiasProyecto, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_academias (
    id                      BIGSERIAL PRIMARY KEY,
    escuela_nivel_id        BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    tipo_academia           TEXT NOT NULL,
    descripcion_academia    TEXT NULL,
    justificacion_academia  TEXT NOT NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT now(),
    updated_at              TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_academias_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_academias_tipo_valido CHECK (tipo_academia IN ('transversal', 'por_recurso_area', 'por_grados', 'por_recursos_afines', 'mixta', 'otros')),
    CONSTRAINT pe_academias_descripcion_coherente CHECK (
        (tipo_academia IN ('mixta', 'otros') AND descripcion_academia IS NOT NULL AND btrim(descripcion_academia) <> '')
        OR (tipo_academia NOT IN ('mixta', 'otros') AND descripcion_academia IS NULL)
    ),
    CONSTRAINT pe_academias_justificacion_no_vacia CHECK (btrim(justificacion_academia) <> '')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_academias');
    }
};
