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
    modalidad              VARCHAR(20) NULL,
    ciclo_escolar          VARCHAR(20) NULL,
    ciclo_escolar_otro     VARCHAR(100) NULL,
    duracion               VARCHAR(20) NULL,
    duracion_otro          VARCHAR(100) NULL,
    sintesis_factibilidad  TEXT NULL,
    created_at             TIMESTAMP NOT NULL DEFAULT now(),
    updated_at             TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT pe_presentacion_modalidad_valida CHECK (modalidad IN ('presencial', 'virtual', 'mixta')),
    CONSTRAINT pe_presentacion_ciclo_valido CHECK (ciclo_escolar IN ('semestral', 'cuatrimestral', 'trimestral', 'bimestral', 'mensual', 'otro')),
    CONSTRAINT pe_presentacion_duracion_valida CHECK (duracion IN ('3_anios', '2_anios', 'otro')),
    CONSTRAINT pe_presentacion_ciclo_otro_coherente CHECK (ciclo_escolar_otro IS NULL OR ciclo_escolar = 'otro'),
    CONSTRAINT pe_presentacion_duracion_otro_coherente CHECK (duracion_otro IS NULL OR duracion = 'otro'),
    CONSTRAINT pe_presentacion_telefono_valido CHECK (telefono IS NULL OR telefono ~ '^[0-9]{10}$'),
    CONSTRAINT pe_presentacion_celular_valido CHECK (celular IS NULL OR celular ~ '^[0-9]{10}$')
);");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS proyecto_educativo_presentacion');
    }
};
