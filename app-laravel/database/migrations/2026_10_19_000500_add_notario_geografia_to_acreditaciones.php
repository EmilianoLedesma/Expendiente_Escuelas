<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entidad federativa y municipio de la notaría de la escritura, ahora como
 * referencias al catálogo geográfico (ADR-017) en lugar del texto libre
 * `notario_localidad`, que se conserva sin tocar para las filas anteriores.
 * Columnas nuevas y nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE acreditaciones_ocupacion_legal ADD COLUMN notario_entidad_federativa_id BIGINT REFERENCES entidades_federativas(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE acreditaciones_ocupacion_legal ADD COLUMN notario_municipio_id BIGINT REFERENCES municipios(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE acreditaciones_ocupacion_legal DROP COLUMN notario_municipio_id');
        DB::statement('ALTER TABLE acreditaciones_ocupacion_legal DROP COLUMN notario_entidad_federativa_id');
    }
};
