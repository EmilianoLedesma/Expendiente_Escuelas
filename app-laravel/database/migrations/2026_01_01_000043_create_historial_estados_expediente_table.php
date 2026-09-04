<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE historial_estados_expediente (
    id                  BIGSERIAL PRIMARY KEY,
    escuela_nivel_id    BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    estado_id           SMALLINT NOT NULL REFERENCES estados_expediente(id),
    comentario          TEXT,
    usuario_sedeq       VARCHAR(150),
    fecha               TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS historial_estados_expediente CASCADE');
    }
};
