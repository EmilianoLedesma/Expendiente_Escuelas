<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 4 del Proyecto Educativo (media superior): "Enfoque Pedagógico". Una fila por nivel. Las dos listas (estrategias
 * centrales y actividades sustantivas) son arreglos JSONB de 2 a 5 textos; los rangos de palabras los valida
 * ReglasEnfoqueProyecto, la base solo cuida la forma (arreglo, 2 a 5 elementos) y que los textos no vengan vacíos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_enfoque (
    id                       BIGSERIAL PRIMARY KEY,
    escuela_nivel_id         BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    propuesta_global         TEXT NULL,
    apropiacion_enfoque      TEXT NULL,
    estrategias_centrales    JSONB NULL,
    actividades_sustantivas  JSONB NULL,
    created_at               TIMESTAMP NOT NULL DEFAULT now(),
    updated_at               TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_enfoque_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_enfoque_propuesta_no_vacia CHECK (propuesta_global IS NULL OR btrim(propuesta_global) <> ''),
    CONSTRAINT pe_enfoque_apropiacion_no_vacia CHECK (apropiacion_enfoque IS NULL OR btrim(apropiacion_enfoque) <> ''),
    CONSTRAINT pe_enfoque_estrategias_forma CHECK (estrategias_centrales IS NULL OR (jsonb_typeof(estrategias_centrales) = 'array' AND jsonb_array_length(estrategias_centrales) BETWEEN 2 AND 5)),
    CONSTRAINT pe_enfoque_actividades_forma CHECK (actividades_sustantivas IS NULL OR (jsonb_typeof(actividades_sustantivas) = 'array' AND jsonb_array_length(actividades_sustantivas) BETWEEN 2 AND 5))
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_enfoque');
    }
};
