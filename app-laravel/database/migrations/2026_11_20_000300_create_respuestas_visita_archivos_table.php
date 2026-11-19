<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** PDF adjuntos a una respuesta de visita. */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE respuestas_visita_archivos (
    id              BIGSERIAL PRIMARY KEY,
    respuesta_id    BIGINT NOT NULL REFERENCES respuestas_visita(id) ON DELETE CASCADE,
    archivo_path    VARCHAR(500) NOT NULL,
    archivo_nombre  VARCHAR(255) NOT NULL
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS respuestas_visita_archivos CASCADE');
    }
};
