<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 13 del Proyecto Educativo (media superior): "Criterios de Acreditación, Promoción y Certificación". Una fila
 * por nivel, creada al guardar el primer bloque que se guarde. Cada bloque se guarda por separado, así que las cinco
 * columnas son NULL hasta que su bloque se guarda; lo guardado nunca es texto en blanco. El rango de palabras lo
 * valida ReglasCriteriosProyecto, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_criterios (
    id                          BIGSERIAL PRIMARY KEY,
    escuela_nivel_id            BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    criterios_acreditacion      TEXT NULL,
    opciones_acreditacion       TEXT NULL,
    criterios_promocion         TEXT NULL,
    criterios_titulacion        TEXT NULL,
    servicios_control_escolar   TEXT NULL,
    created_at                  TIMESTAMP NOT NULL DEFAULT now(),
    updated_at                  TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_criterios_nivel_unico UNIQUE (escuela_nivel_id),
    CONSTRAINT pe_criterios_acreditacion_no_vacio CHECK (criterios_acreditacion IS NULL OR btrim(criterios_acreditacion) <> ''),
    CONSTRAINT pe_criterios_opciones_no_vacio CHECK (opciones_acreditacion IS NULL OR btrim(opciones_acreditacion) <> ''),
    CONSTRAINT pe_criterios_promocion_no_vacio CHECK (criterios_promocion IS NULL OR btrim(criterios_promocion) <> ''),
    CONSTRAINT pe_criterios_titulacion_no_vacio CHECK (criterios_titulacion IS NULL OR btrim(criterios_titulacion) <> ''),
    CONSTRAINT pe_criterios_servicios_no_vacio CHECK (servicios_control_escolar IS NULL OR btrim(servicios_control_escolar) <> '')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_criterios');
    }
};
