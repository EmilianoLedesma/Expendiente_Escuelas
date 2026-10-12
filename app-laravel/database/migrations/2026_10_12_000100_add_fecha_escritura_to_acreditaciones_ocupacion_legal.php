<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fecha de la escritura pública (dato normativo propio, distinto de la fecha
 * de inscripción en el RPP). Columna nueva y nullable: las acreditaciones ya
 * capturadas conservan NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE acreditaciones_ocupacion_legal ADD COLUMN fecha_escritura DATE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE acreditaciones_ocupacion_legal DROP COLUMN fecha_escritura');
    }
};
