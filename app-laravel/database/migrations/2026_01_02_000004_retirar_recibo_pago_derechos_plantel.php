<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * WS-5b (O1): recibo_pago_derechos_plantel era un marcador de WS-5a; el
 * recibo real es por nivel (recibo_pago_derechos, Paso 2.4). Ninguna de las
 * tres tablas de documentos tiene ON DELETE en tipo_documento_id, así que
 * primero se borran sus filas (solo documentos_plantel debería tenerlas; las
 * otras dos se incluyen por seguridad). Los archivos en disco NO se borran. El
 * dueño confirmó el conteo en dev antes de correrla (compuerta G0 del plan de
 * WS-5b).
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipoId = DB::table('tipos_documentos')->where('clave', 'recibo_pago_derechos_plantel')->value('id');

        if ($tipoId === null) {
            return;
        }

        foreach (['documentos_plantel', 'documentos_escuela', 'documentos_escuela_nivel'] as $tabla) {
            DB::table($tabla)->where('tipo_documento_id', $tipoId)->delete();
        }

        DB::table('tipos_documentos')->where('id', $tipoId)->delete();
    }

    public function down(): void
    {
        // Irreversible a propósito: las filas borradas no se reconstruyen.
    }
};
