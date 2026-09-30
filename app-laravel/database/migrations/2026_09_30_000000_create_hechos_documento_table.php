<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Not in DDL v3: new table, touches none of the existing 44. Justification
 * and open questions in docs/reports/2026-09-30-evaluacion-motor-validacion.md
 * §4 and docs/decisions/PENDIENTE-motor-validacion-hechos.md (P3, P4, P7).
 *
 * A fact is tied to the exact uploaded file (archivo_path is unique per
 * upload since WS-2.3), not just to the documentos_escuela row, because
 * RegistrarDocumento replaces the file in place on the same row. Facts of a
 * replaced file stay as history and are ignored when validating.
 *
 * Raw DDL via DB::unprepared, same as the 2026_01_01_* migrations, to keep
 * the CHECK constraints verbatim.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE hechos_documento (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_id          BIGINT NOT NULL REFERENCES escuelas(id) ON DELETE CASCADE,
    tipo_documento_id   INTEGER NOT NULL REFERENCES tipos_documentos(id),
    archivo_path        VARCHAR(500) NOT NULL,
    tipo_hecho          VARCHAR(30) NOT NULL
                            CHECK (tipo_hecho IN (\'nombre_titular\', \'curp\')),
    valor               TEXT NOT NULL CHECK (btrim(valor) <> \'\'),
    metodo              VARCHAR(20) NOT NULL
                            CHECK (metodo IN (\'captura_manual\')),
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE (escuela_id, tipo_documento_id, archivo_path, tipo_hecho)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS hechos_documento CASCADE');
    }
};
