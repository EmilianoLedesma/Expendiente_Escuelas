<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 9 del Proyecto Educativo (media superior): "Perfil de Egreso". Una fila por nivel; el texto es NULL hasta que
 * se guarda. El rango de palabras lo valida ReglasEgresoProyecto, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_egreso (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    perfil_egreso     TEXT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_egreso_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_egreso_perfil_no_vacio CHECK (perfil_egreso IS NULL OR btrim(perfil_egreso) <> '')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_egreso');
    }
};
