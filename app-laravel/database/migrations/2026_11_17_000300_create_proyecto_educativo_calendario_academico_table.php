<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 18 del Proyecto Educativo (media superior): "Calendario Académico". Una fila por nivel: un PDF (ruta, nombre
 * original, páginas; las tres columnas juntas o ninguna) y una descripción opcional. El archivo vive en el disco
 * `documentos`; borrarlo al purgar el trámite o quitar el nivel es trabajo de PurgarTramite y RegistrarNivelesSeleccionados
 * (el cascade solo borra la fila). Que sea PDF lo valida la capa de aplicación leyendo el contenido, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_calendario_academico (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    archivo_path      VARCHAR(255) NULL,
    archivo_nombre    VARCHAR(255) NULL,
    archivo_paginas   SMALLINT NULL,
    descripcion       TEXT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_calendario_academico_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_calendario_academico_descripcion_no_vacia CHECK (descripcion IS NULL OR btrim(descripcion) <> ''),
    CONSTRAINT pe_calendario_academico_archivo_completo CHECK (
        (archivo_path IS NULL AND archivo_nombre IS NULL AND archivo_paginas IS NULL)
        OR (archivo_path IS NOT NULL AND archivo_nombre IS NOT NULL AND archivo_paginas >= 1))
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_calendario_academico');
    }
};
