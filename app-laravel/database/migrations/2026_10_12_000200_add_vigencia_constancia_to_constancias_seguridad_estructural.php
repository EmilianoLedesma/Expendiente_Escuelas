<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vigencia de la constancia misma, dato normativo distinto de la vigencia del
 * registro del perito (perito_registro_vigencia). Columna nueva y nullable:
 * las constancias ya capturadas conservan NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE constancias_seguridad_estructural ADD COLUMN vigencia_constancia DATE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE constancias_seguridad_estructural DROP COLUMN vigencia_constancia');
    }
};
