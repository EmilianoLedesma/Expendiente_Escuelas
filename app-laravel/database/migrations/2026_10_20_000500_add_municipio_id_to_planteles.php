<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Municipio del plantel como referencia al catálogo geográfico local (ADR-008, que lo decidió el 2026-10-07, y
 * ADR-017, cuyas tablas son las que se referencian). La columna de texto `municipio` se conserva sin tocar: la usan
 * el motor de validación, las proyecciones y las filas anteriores, y desde ahora se deriva del catálogo en el
 * servidor cuando llega un id. Columna nueva y nullable: ningún plantel existente cambia. La vinculación de los
 * planteles históricos no se hace aquí (el catálogo se importa después de migrar): `php artisan
 * geografia:vincular-municipios` la propone en modo simulación y solo escribe coincidencias inequívocas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE planteles ADD COLUMN municipio_id BIGINT REFERENCES municipios(id) ON DELETE SET NULL');
        DB::statement('CREATE INDEX planteles_municipio_id_index ON planteles (municipio_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS planteles_municipio_id_index');
        DB::statement('ALTER TABLE planteles DROP COLUMN municipio_id');
    }
};
