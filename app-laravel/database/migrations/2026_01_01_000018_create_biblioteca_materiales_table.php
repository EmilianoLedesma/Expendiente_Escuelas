<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE biblioteca_materiales (
    instalacion_espacio_id     BIGINT NOT NULL REFERENCES instalaciones_espacios(id) ON DELETE CASCADE,
    tipo_material_id           SMALLINT NOT NULL REFERENCES tipos_material_biblioteca(id),
    numero_titulos              INTEGER,
    numero_volumenes             INTEGER,
    PRIMARY KEY (instalacion_espacio_id, tipo_material_id)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS biblioteca_materiales CASCADE');
    }
};
