<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 11 del Proyecto Educativo (media superior): "Formación Docente", bloque "Formación Docente Inicial".
 * `tipo` deja la puerta abierta a los bloques que siguen con la misma forma (hoy solo 'inicial').
 *  - formacion_politica: una fila por nivel y tipo (el texto de la política global).
 *  - formacion_acciones: una fila por acción formativa, en orden. Horas como TIME, fechas como DATE, días como lista JSONB;
 *    la base exige lo que no depende de contar palabras (rangos, formato "Otro", fin después del inicio).
 * Los rangos de palabras los valida ReglasFormacionProyecto, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_formacion_politica (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    tipo              TEXT NOT NULL,
    politica          TEXT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_formacion_politica_unica UNIQUE (escuela_nivel_id, tipo),
    CONSTRAINT pe_formacion_politica_no_vacia CHECK (politica IS NULL OR btrim(politica) <> '')
);");

        DB::unprepared("CREATE TABLE proyecto_educativo_formacion_acciones (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    tipo              TEXT NOT NULL,
    orden             SMALLINT NOT NULL,
    nombre            VARCHAR(150) NOT NULL,
    proposito         TEXT NOT NULL,
    participantes     TEXT NOT NULL,
    formato           TEXT NOT NULL,
    formato_otro      VARCHAR(60) NULL,
    duracion_horas    SMALLINT NOT NULL,
    dias              JSONB NOT NULL,
    hora_inicio       TIME NOT NULL,
    hora_fin          TIME NOT NULL,
    fecha_inicio      DATE NOT NULL,
    fecha_fin         DATE NOT NULL,
    temario           TEXT NOT NULL,
    evaluacion        TEXT NOT NULL,
    recursos          TEXT NOT NULL,
    perfil_ponente    TEXT NOT NULL,
    reconocimiento    TEXT NOT NULL,
    otros             TEXT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_formacion_accion_orden_unico UNIQUE (escuela_nivel_id, tipo, orden),
    CONSTRAINT pe_formacion_accion_orden_positivo CHECK (orden >= 1),
    CONSTRAINT pe_formacion_accion_nombre_no_vacio CHECK (btrim(nombre) <> ''),
    CONSTRAINT pe_formacion_accion_formato CHECK (formato IN ('taller', 'curso', 'seminario', 'diplomado', 'otro')),
    CONSTRAINT pe_formacion_accion_formato_otro CHECK ((formato = 'otro') = (formato_otro IS NOT NULL)),
    CONSTRAINT pe_formacion_accion_duracion CHECK (duracion_horas BETWEEN 1 AND 999),
    CONSTRAINT pe_formacion_accion_dias CHECK (jsonb_typeof(dias) = 'array' AND jsonb_array_length(dias) BETWEEN 1 AND 7),
    CONSTRAINT pe_formacion_accion_horas CHECK (hora_fin > hora_inicio),
    CONSTRAINT pe_formacion_accion_fechas CHECK (fecha_fin >= fecha_inicio)
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_formacion_acciones');
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_formacion_politica');
    }
};
