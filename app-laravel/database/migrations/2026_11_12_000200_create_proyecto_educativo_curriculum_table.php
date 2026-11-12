<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 7 del Proyecto Educativo (media superior): "Currículum Ampliado" (Recursos Socioemocionales). Una fila por nivel;
 * por cada uno de los cinco ámbitos, la respuesta (¿la trabajan?) y su justificación, ambas NULL hasta que se guarda
 * (todo o nada), y nunca una sin la otra. El rango de palabras lo valida ReglasCurriculumProyecto, no la base.
 */
return new class extends Migration
{
    private const AMBITOS = ['practica_ciudadana', 'salud', 'actividad_fisica', 'sexualidad_genero', 'artistico_cultural'];

    public function up(): void
    {
        $columnas = implode("\n", array_map(fn (string $a) => "    {$a}_trabaja BOOLEAN NULL,\n    {$a}_justificacion TEXT NULL,", self::AMBITOS));
        $checks = implode(",\n", array_map(fn (string $a) => "    CONSTRAINT pe_curriculum_{$a}_completo CHECK (({$a}_trabaja IS NULL) = ({$a}_justificacion IS NULL)),\n    CONSTRAINT pe_curriculum_{$a}_no_vacio CHECK ({$a}_justificacion IS NULL OR btrim({$a}_justificacion) <> '')", self::AMBITOS));

        DB::unprepared("CREATE TABLE proyecto_educativo_curriculum (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
{$columnas}
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_curriculum_nivel_unico UNIQUE (escuela_nivel_id),
{$checks}
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_curriculum');
    }
};
