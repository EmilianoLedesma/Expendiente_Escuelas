<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE personal (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    cargo_puesto_id     INTEGER REFERENCES cargos_puestos(id),
    nombre              VARCHAR(200),
    nacionalidad        VARCHAR(100),
    sexo                CHAR(1) CHECK (sexo IN (\'M\', \'F\')),
    estudios            VARCHAR(200),
    cedula_o_documento  VARCHAR(100),
    perfil_validado     BOOLEAN,
    created_at          TIMESTAMP NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS personal CASCADE');
    }
};
