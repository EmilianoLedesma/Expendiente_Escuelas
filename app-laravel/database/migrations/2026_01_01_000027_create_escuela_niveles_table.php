<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE escuela_niveles (
    id                          BIGSERIAL PRIMARY KEY,
    escuela_id                  BIGINT NOT NULL REFERENCES escuelas(id) ON DELETE CASCADE,
    nivel_educativo_id          SMALLINT NOT NULL REFERENCES niveles_educativos(id),
    estado_id                   SMALLINT NOT NULL REFERENCES estados_expediente(id),

    folio_expediente            VARCHAR(20) UNIQUE,
    tipo_tramite                VARCHAR(20) NOT NULL
                                    CHECK (tipo_tramite IN (\'alta_nueva\', \'reincorporacion\')),
    modalidad                   VARCHAR(20)
                                    CHECK (modalidad IN (\'escolarizada\', \'no_escolarizada\', \'mixta\', \'virtual\')),
    plan_estudios_referencia    VARCHAR(200),
    turno                       VARCHAR(20) CHECK (turno IN (\'matutino\', \'vespertino\', \'mixto\')),
    tipo_alumnado                VARCHAR(20) CHECK (tipo_alumnado IN (\'mixto\', \'femenino\', \'masculino\')),
    plataforma_educativa_tipo   VARCHAR(20) CHECK (plataforma_educativa_tipo IN (\'propia\', \'rentada\')),

    fecha_inicio_tramite        DATE NOT NULL DEFAULT CURRENT_DATE,
    fecha_resolucion            DATE,

    created_at                  TIMESTAMP NOT NULL DEFAULT now(),
    updated_at                  TIMESTAMP NOT NULL DEFAULT now(),

    UNIQUE (escuela_id, nivel_educativo_id)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS escuela_niveles CASCADE');
    }
};
