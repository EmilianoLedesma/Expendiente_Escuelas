<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Title count written in the "Relación del acervo bibliográfico" uploaded in
 * Paso 2.4 (WS-5b), read by the per-level acervo check (ADR-007). Not in DDL
 * v3: a new extension table with the shape of recibos_pago_derechos (one row
 * per documentos_escuela_nivel row, overwritten on re-upload). The declared
 * side is biblioteca_materiales.numero_titulos (INTEGER), mirrored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE relaciones_acervo_bibliografico (
    documento_escuela_nivel_id  BIGINT PRIMARY KEY REFERENCES documentos_escuela_nivel(id) ON DELETE CASCADE,
    numero_titulos              INTEGER NOT NULL CHECK (numero_titulos >= 0)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS relaciones_acervo_bibliografico CASCADE');
    }
};
