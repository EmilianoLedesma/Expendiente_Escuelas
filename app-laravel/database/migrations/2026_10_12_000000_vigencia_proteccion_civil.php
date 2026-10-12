<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El Visto Bueno de Protección Civil vence a los 30 días de su emisión.
 * TiposDocumentosSeeder usa insertOrIgnore y nunca actualiza filas existentes,
 * así que una BD ya sembrada conservaría vigencia_max_dias NULL (mismo caso
 * que 2026_01_02_000003). En una BD nueva el catálogo está vacío aquí (no-op)
 * y el seeder siembra el 30.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tipos_documentos')
            ->where('clave', 'visto_bueno_proteccion_civil')
            ->update(['vigencia_max_dias' => 30, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('tipos_documentos')
            ->where('clave', 'visto_bueno_proteccion_civil')
            ->update(['vigencia_max_dias' => null, 'updated_at' => now()]);
    }
};
