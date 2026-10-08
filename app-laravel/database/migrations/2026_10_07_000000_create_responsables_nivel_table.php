<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE responsables_nivel (
    id                          BIGSERIAL PRIMARY KEY,
    user_id                     BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    escuela_nivel_id            BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    invitado_por_solicitante_id BIGINT NOT NULL REFERENCES solicitantes(id) ON DELETE RESTRICT,
    created_at                  TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE (user_id, escuela_nivel_id)
);
CREATE INDEX idx_responsables_nivel_escuela_nivel ON responsables_nivel(escuela_nivel_id);
CREATE INDEX idx_responsables_nivel_invitado_por ON responsables_nivel(invitado_por_solicitante_id);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS responsables_nivel CASCADE');
    }
};
