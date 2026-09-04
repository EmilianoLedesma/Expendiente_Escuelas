<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE ternas_nombres (
    id                      BIGSERIAL PRIMARY KEY,
    escuela_id              BIGINT NOT NULL REFERENCES escuelas(id) ON DELETE CASCADE,
    numero_propuesta        SMALLINT NOT NULL CHECK (numero_propuesta BETWEEN 1 AND 3),
    nombre_propuesto        VARCHAR(200) NOT NULL,
    valido_marca_comercial  BOOLEAN,
    valido_registro_sedeq   BOOLEAN,
    created_at              TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE (escuela_id, numero_propuesta)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS ternas_nombres CASCADE');
    }
};
