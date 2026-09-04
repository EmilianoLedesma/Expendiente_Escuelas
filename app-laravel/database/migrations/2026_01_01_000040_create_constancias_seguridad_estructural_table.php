<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE constancias_seguridad_estructural (
    documento_plantel_id        BIGINT PRIMARY KEY REFERENCES documentos_plantel(id) ON DELETE CASCADE,
    perito_nombre                VARCHAR(200),
    perito_cedula_profesional    VARCHAR(50),
    perito_registro_dro          VARCHAR(50),
    perito_registro_autoridad    VARCHAR(150),
    perito_registro_vigencia     DATE
    -- Regla de validación cruzada (año registro perito = año emisión constancia) se valida en aplicación.
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS constancias_seguridad_estructural CASCADE');
    }
};
