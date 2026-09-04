<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE recibos_pago_derechos (
    documento_escuela_nivel_id  BIGINT PRIMARY KEY REFERENCES documentos_escuela_nivel(id) ON DELETE CASCADE,
    folio                        VARCHAR(50),
    monto                        NUMERIC(10,2),
    fecha_pago                   DATE,
    portal_referencia            VARCHAR(200)  -- ej. portal-tributario.queretaro.gob.mx
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS recibos_pago_derechos CASCADE');
    }
};
