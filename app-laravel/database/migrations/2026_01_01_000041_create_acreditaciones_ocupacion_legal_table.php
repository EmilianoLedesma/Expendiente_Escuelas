<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE acreditaciones_ocupacion_legal (
    documento_plantel_id     BIGINT PRIMARY KEY REFERENCES documentos_plantel(id) ON DELETE CASCADE,
    tipo                      VARCHAR(20) NOT NULL
                                CHECK (tipo IN (\'escritura_publica\', \'arrendamiento\', \'comodato\', \'otro\')),
    numero_escritura          VARCHAR(50),
    notario_nombre            VARCHAR(150),
    notario_numero            VARCHAR(20),
    notario_localidad         VARCHAR(100),
    folio_rpp                 VARCHAR(50),
    fecha_inscripcion_rpp     DATE,
    arrendador_comodante      VARCHAR(200),
    arrendatario_comodatario  VARCHAR(200),
    fecha_contrato            DATE,
    vigencia_contrato         DATE,
    uso_autorizado            VARCHAR(200),
    ratificado_notario        BOOLEAN,
    otro_especifique          VARCHAR(200),
    observaciones             TEXT
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS acreditaciones_ocupacion_legal CASCADE');
    }
};
