<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 1 del Proyecto Educativo (media superior): "Presentación del Proyecto Académico".
 * Una fila por nivel. Todo es NULL porque la sección se guarda como borrador; "completa" lo decide
 * ReglasPresentacionProyecto, no la base. Los CHECK solo cubren los enums.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE proyecto_educativo_presentacion (
    id                     BIGSERIAL PRIMARY KEY,
    escuela_nivel_id       BIGINT NOT NULL UNIQUE REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    fecha_entrega          DATE NULL,
    nombre_institucion     VARCHAR(255) NULL,
    fecha_apertura         DATE NULL,
    domicilio_completo     VARCHAR(500) NULL,
    representante_legal    VARCHAR(255) NULL,
    correo                 VARCHAR(255) NULL,
    telefono               VARCHAR(10) NULL,
    celular                VARCHAR(10) NULL,
    pagina_web             VARCHAR(255) NULL,
    modalidad              VARCHAR(20) NULL CHECK (modalidad IN ('presencial', 'virtual', 'mixta')),
    ciclo_escolar          VARCHAR(20) NULL CHECK (ciclo_escolar IN ('semestral', 'cuatrimestral', 'trimestral', 'bimestral', 'mensual', 'otro')),
    ciclo_escolar_otro     VARCHAR(100) NULL,
    duracion               VARCHAR(20) NULL CHECK (duracion IN ('3_anios', '2_anios', 'otro')),
    duracion_otro          VARCHAR(100) NULL,
    sintesis_factibilidad  TEXT NULL,
    created_at             TIMESTAMP NOT NULL DEFAULT now(),
    updated_at             TIMESTAMP NOT NULL DEFAULT now()
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_presentacion');
    }
};
