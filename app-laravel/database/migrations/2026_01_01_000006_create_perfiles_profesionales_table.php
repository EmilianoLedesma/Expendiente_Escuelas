<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE perfiles_profesionales (
    id                      SERIAL PRIMARY KEY,
    cargo_puesto_id         INTEGER NOT NULL REFERENCES cargos_puestos(id),
    asignatura_id           INTEGER REFERENCES asignaturas(id),  -- NULL salvo Secundaria
    carrera_aceptada        VARCHAR(200) NOT NULL,
    documento_acreditacion  VARCHAR(20) NOT NULL
                                CHECK (documento_acreditacion IN (\'titulo_cedula\', \'certificado\')),
    created_at              TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS perfiles_profesionales CASCADE');
    }
};
