<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE responsables_legales (
    id                          BIGSERIAL PRIMARY KEY,
    escuela_id                  BIGINT NOT NULL UNIQUE REFERENCES escuelas(id) ON DELETE CASCADE,
    tipo_persona                VARCHAR(20) NOT NULL
                                    CHECK (tipo_persona IN (\'fisica\', \'fisica_con_gestor\', \'moral\')),
    domicilio_notificaciones    VARCHAR(250),
    persona_autorizada_recoger  VARCHAR(200),
    created_at                  TIMESTAMP NOT NULL DEFAULT now(),
    updated_at                  TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS responsables_legales CASCADE');
    }
};
