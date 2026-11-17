<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 16 del Proyecto Educativo (media superior): "Programas de Estudios". Una fila por nivel, creada al guardar (todo o
 * nada: periodicidad, modalidad y la respuesta Sí/No nunca son NULL). Con "retoma los programas de la DGB completos" la
 * justificación y el PDF no existen; sin retomarlos son obligatorios (el PDF: ruta, nombre original y páginas, las tres
 * columnas juntas). "Otra" periodicidad guarda su texto, y solo con "otra". El archivo vive en el disco `documentos`;
 * borrarlo al purgar el trámite o quitar el nivel es trabajo de PurgarTramite y RegistrarNivelesSeleccionados (el cascade
 * solo borra la fila). Que sea PDF y el rango de palabras los valida la capa de aplicación, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_programas_estudios (
    id                 BIGSERIAL PRIMARY KEY,
    escuela_nivel_id   BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    periodicidad       TEXT NOT NULL,
    periodicidad_otra  VARCHAR(60) NULL,
    modalidad          TEXT NOT NULL,
    retoma_dgb         BOOLEAN NOT NULL,
    justificacion      TEXT NULL,
    archivo_path       VARCHAR(255) NULL,
    archivo_nombre     VARCHAR(255) NULL,
    archivo_paginas    SMALLINT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT now(),
    updated_at         TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_programas_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_programas_periodicidad_valida CHECK (periodicidad IN ('semestral', 'cuatrimestral', 'trimestral', 'bimestral', 'mensual', 'otra')),
    CONSTRAINT pe_programas_modalidad_valida CHECK (modalidad IN ('presencial', 'virtual', 'mixta')),
    CONSTRAINT pe_programas_otra_coherente CHECK (
        (periodicidad = 'otra' AND periodicidad_otra IS NOT NULL AND btrim(periodicidad_otra) <> '')
        OR (periodicidad <> 'otra' AND periodicidad_otra IS NULL)
    ),
    CONSTRAINT pe_programas_adaptacion_coherente CHECK (
        (retoma_dgb AND justificacion IS NULL AND archivo_path IS NULL AND archivo_nombre IS NULL AND archivo_paginas IS NULL)
        OR (NOT retoma_dgb AND justificacion IS NOT NULL AND btrim(justificacion) <> ''
            AND archivo_path IS NOT NULL AND archivo_nombre IS NOT NULL AND archivo_paginas >= 1)
    )
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_programas_estudios');
    }
};
