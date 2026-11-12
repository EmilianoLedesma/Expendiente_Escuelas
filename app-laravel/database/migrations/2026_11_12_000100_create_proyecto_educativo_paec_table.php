<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 6 del Proyecto Educativo (media superior): "Programa Aula, Escuela y Comunidad (PAEC)". Una fila por nivel:
 * el diagnóstico (texto) y dos PDF de 1 a 2 páginas (hoja de ruta del PEC y planeación del abordaje). Cada archivo se
 * guarda completo (ruta, nombre original, páginas) o no se guarda. Los archivos viven en el disco `documentos`; borrarlos
 * al purgar el trámite o quitar el nivel es trabajo de PurgarTramite y RegistrarNivelesSeleccionados (el cascade solo
 * borra la fila).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_paec (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    diagnostico         TEXT NULL,
    hoja_ruta_path      VARCHAR(255) NULL,
    hoja_ruta_nombre    VARCHAR(255) NULL,
    hoja_ruta_paginas   SMALLINT NULL,
    planeacion_path     VARCHAR(255) NULL,
    planeacion_nombre   VARCHAR(255) NULL,
    planeacion_paginas  SMALLINT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_paec_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_paec_diagnostico_no_vacio CHECK (diagnostico IS NULL OR btrim(diagnostico) <> ''),
    CONSTRAINT pe_paec_hoja_ruta_completa CHECK (
        (hoja_ruta_path IS NULL AND hoja_ruta_nombre IS NULL AND hoja_ruta_paginas IS NULL)
        OR (hoja_ruta_path IS NOT NULL AND hoja_ruta_nombre IS NOT NULL AND hoja_ruta_paginas BETWEEN 1 AND 2)),
    CONSTRAINT pe_paec_planeacion_completa CHECK (
        (planeacion_path IS NULL AND planeacion_nombre IS NULL AND planeacion_paginas IS NULL)
        OR (planeacion_path IS NOT NULL AND planeacion_nombre IS NOT NULL AND planeacion_paginas BETWEEN 1 AND 2))
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_paec');
    }
};
