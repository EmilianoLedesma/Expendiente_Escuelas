<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 10 del Proyecto Educativo (media superior): "Perfil Docente". Una fila por nivel; el texto es NULL hasta que
 * se guarda. El rango de palabras lo valida ReglasPerfilDocenteProyecto, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_perfil_docente (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    habilidades_virtudes     TEXT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_perfil_docente_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_perfil_docente_perfil_no_vacio CHECK (habilidades_virtudes IS NULL OR btrim(habilidades_virtudes) <> '')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_perfil_docente');
    }
};
