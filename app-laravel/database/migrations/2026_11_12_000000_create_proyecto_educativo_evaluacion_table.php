<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 5 del Proyecto Educativo (media superior): "Modelo de Evaluación". Una fila por nivel; los siete textos son
 * NULL hasta que se guardan (se guarda todo o nada). Los rangos de palabras los valida ReglasEvaluacionProyecto, no la base.
 */
return new class extends Migration
{
    private const CAMPOS = ['evaluacion_diagnostica', 'evaluacion_formativa', 'evaluacion_sumativa', 'heteroevaluacion', 'coevaluacion', 'autoevaluacion', 'elementos_porcentajes'];

    public function up(): void
    {
        $columnas = implode("\n", array_map(fn (string $c) => "    {$c} TEXT NULL,", self::CAMPOS));
        $checks = implode(",\n", array_map(fn (string $c) => "    CONSTRAINT pe_evaluacion_{$c}_no_vacio CHECK ({$c} IS NULL OR btrim({$c}) <> '')", self::CAMPOS));

        DB::unprepared("CREATE TABLE proyecto_educativo_evaluacion (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
{$columnas}
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_evaluacion_nivel_unico UNIQUE (escuela_nivel_id),
{$checks}
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_evaluacion');
    }
};
