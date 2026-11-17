<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 19 del Proyecto Educativo (media superior): "Servicios de Biblioteca y Acervo Bibliográfico". Una fila por nivel,
 * creada al guardar el primer bloque que se guarde. Cada bloque se guarda por separado y todo o nada, así que las columnas
 * de un bloque son todas NULL o todas con valor (salvo las condicionales): el tipo de biblioteca virtual solo existe con
 * biblioteca virtual o mixta, el perfil del encargado solo con encargado y el texto del programa de lectura solo con
 * programa. Ningún texto guardado puede estar en blanco; el rango de palabras lo valida ReglasBibliotecaProyecto, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_biblioteca (
    id                                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id                    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    biblioteca_tipo                     TEXT NULL,
    biblioteca_virtual_tipo             TEXT NULL,
    biblioteca_tiene_encargado          BOOLEAN NULL,
    biblioteca_perfil_encargado         TEXT NULL,
    biblioteca_prestamo                 TEXT NULL,
    biblioteca_tiene_programa_lectura   BOOLEAN NULL,
    biblioteca_programa_lectura         TEXT NULL,
    biblioteca_otros_programas          TEXT NULL,
    acervo_clasificacion                TEXT NULL,
    acervo_titulos_basicos              TEXT NULL,
    acervo_titulos_lectura              TEXT NULL,
    created_at                          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at                          TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_biblioteca_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_biblioteca_tipo_valido CHECK (biblioteca_tipo IS NULL OR biblioteca_tipo IN ('fisica', 'virtual', 'mixta')),
    CONSTRAINT pe_biblioteca_virtual_tipo_valido CHECK (biblioteca_virtual_tipo IS NULL OR biblioteca_virtual_tipo IN ('propia', 'dominio_publico', 'mixta')),
    CONSTRAINT pe_biblioteca_virtual_coherente CHECK (
        (biblioteca_tipo IS NULL AND biblioteca_virtual_tipo IS NULL)
        OR (biblioteca_tipo = 'fisica' AND biblioteca_virtual_tipo IS NULL)
        OR (biblioteca_tipo IN ('virtual', 'mixta') AND biblioteca_virtual_tipo IS NOT NULL)
    ),
    CONSTRAINT pe_biblioteca_encargado_coherente CHECK (
        (biblioteca_tiene_encargado IS NULL AND biblioteca_perfil_encargado IS NULL)
        OR (biblioteca_tiene_encargado = false AND biblioteca_perfil_encargado IS NULL)
        OR (biblioteca_tiene_encargado = true AND biblioteca_perfil_encargado IS NOT NULL)
    ),
    CONSTRAINT pe_biblioteca_programa_coherente CHECK (
        (biblioteca_tiene_programa_lectura IS NULL AND biblioteca_programa_lectura IS NULL)
        OR (biblioteca_tiene_programa_lectura = false AND biblioteca_programa_lectura IS NULL)
        OR (biblioteca_tiene_programa_lectura = true AND biblioteca_programa_lectura IS NOT NULL)
    ),
    CONSTRAINT pe_biblioteca_bloque1_completo CHECK (
        (biblioteca_tipo IS NULL) = (biblioteca_tiene_encargado IS NULL)
        AND (biblioteca_tipo IS NULL) = (biblioteca_prestamo IS NULL)
    ),
    CONSTRAINT pe_biblioteca_bloque2_completo CHECK (
        (biblioteca_tiene_programa_lectura IS NULL) = (biblioteca_otros_programas IS NULL)
        AND (biblioteca_tiene_programa_lectura IS NULL) = (acervo_clasificacion IS NULL)
        AND (biblioteca_tiene_programa_lectura IS NULL) = (acervo_titulos_basicos IS NULL)
        AND (biblioteca_tiene_programa_lectura IS NULL) = (acervo_titulos_lectura IS NULL)
    ),
    CONSTRAINT pe_biblioteca_textos_no_vacios CHECK (
        (biblioteca_perfil_encargado IS NULL OR btrim(biblioteca_perfil_encargado) <> '')
        AND (biblioteca_prestamo IS NULL OR btrim(biblioteca_prestamo) <> '')
        AND (biblioteca_programa_lectura IS NULL OR btrim(biblioteca_programa_lectura) <> '')
        AND (biblioteca_otros_programas IS NULL OR btrim(biblioteca_otros_programas) <> '')
        AND (acervo_clasificacion IS NULL OR btrim(acervo_clasificacion) <> '')
        AND (acervo_titulos_basicos IS NULL OR btrim(acervo_titulos_basicos) <> '')
        AND (acervo_titulos_lectura IS NULL OR btrim(acervo_titulos_lectura) <> '')
    )
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_biblioteca');
    }
};
