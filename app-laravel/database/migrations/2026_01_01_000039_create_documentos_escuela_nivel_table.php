<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE documentos_escuela_nivel (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    tipo_documento_id   INTEGER NOT NULL REFERENCES tipos_documentos(id),
    archivo_path        VARCHAR(500),
    fecha_emision       DATE,
    fecha_vigencia      DATE,
    estado_validacion   VARCHAR(20) NOT NULL DEFAULT \'pendiente\'
                            CHECK (estado_validacion IN (\'pendiente\', \'validado\', \'rechazado\')),
    observaciones       TEXT,
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS documentos_escuela_nivel CASCADE');
    }
};
