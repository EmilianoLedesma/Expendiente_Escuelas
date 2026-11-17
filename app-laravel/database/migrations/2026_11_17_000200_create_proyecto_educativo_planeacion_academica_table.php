<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 17 del Proyecto Educativo (media superior): "Planeación Académica". Una fila por nivel, creada al guardar (todo o
 * nada: el PDF y la descripción son requeridos, así que nunca hay NULL). El PDF guarda ruta, nombre original y páginas.
 * El archivo vive en el disco `documentos`; borrarlo al purgar el trámite o quitar el nivel es trabajo de PurgarTramite y
 * RegistrarNivelesSeleccionados (el cascade solo borra la fila). Que sea PDF y el rango de palabras los valida la capa de
 * aplicación, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_planeacion_academica (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    archivo_path      VARCHAR(255) NOT NULL,
    archivo_nombre    VARCHAR(255) NOT NULL,
    archivo_paginas   SMALLINT NOT NULL,
    descripcion       TEXT NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_planeacion_academica_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_planeacion_academica_paginas_validas CHECK (archivo_paginas >= 1),
    CONSTRAINT pe_planeacion_academica_descripcion_no_vacia CHECK (btrim(descripcion) <> '')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_planeacion_academica');
    }
};
