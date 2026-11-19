<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Visita de inspección (fase 1 posterior a la aprobación documental): una fila por visita de un nivel. Máximo una
 * `programada` por nivel (índice parcial); `resultado` solo existe cuando la visita está `realizada`, y `subsanar_hasta`
 * solo cuando el resultado es `con_observaciones`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE visitas_inspeccion (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    numero            SMALLINT NOT NULL,
    programada_para   TIMESTAMP NOT NULL,
    inspector         VARCHAR(150) NOT NULL,
    estado            VARCHAR(20) NOT NULL DEFAULT 'programada',
    resultado         VARCHAR(20) NULL,
    realizada_el      DATE NULL,
    subsanar_hasta    DATE NULL,
    acta_path         VARCHAR(500) NULL,
    acta_nombre       VARCHAR(255) NULL,
    programada_por    BIGINT NOT NULL REFERENCES users(id),
    registrada_por    BIGINT NULL REFERENCES users(id),
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT visitas_inspeccion_numero_unico UNIQUE (escuela_nivel_id, numero),
    CONSTRAINT visitas_inspeccion_numero_valido CHECK (numero >= 1),
    CONSTRAINT visitas_inspeccion_estado_valido CHECK (estado IN ('programada', 'realizada')),
    CONSTRAINT visitas_inspeccion_resultado_valido CHECK (resultado IS NULL OR resultado IN ('cumple', 'con_observaciones')),
    CONSTRAINT visitas_inspeccion_resultado_iff_realizada CHECK ((estado = 'realizada') = (resultado IS NOT NULL)),
    CONSTRAINT visitas_inspeccion_subsanar_iff_observaciones CHECK (COALESCE(resultado = 'con_observaciones', false) = (subsanar_hasta IS NOT NULL))
);
CREATE UNIQUE INDEX visitas_inspeccion_una_programada ON visitas_inspeccion (escuela_nivel_id) WHERE estado = 'programada';");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS visitas_inspeccion CASCADE');
    }
};
