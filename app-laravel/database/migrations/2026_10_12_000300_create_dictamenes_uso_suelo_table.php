<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Datos normativos del Dictamen de Uso de Suelo (autoridad que lo expide, folio
 * y giro que autoriza). Tabla de extensión de documentos_plantel, mismo patrón
 * que constancias_seguridad_estructural y acreditaciones_ocupacion_legal. No
 * está en docs/ddl_sistema_incorporacion_v3.sql: adición autorizada por el
 * dueño del proyecto (2026-10-12). Columnas nullable: los dictámenes ya
 * capturados no tienen estos datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE dictamenes_uso_suelo (
    documento_plantel_id  BIGINT PRIMARY KEY REFERENCES documentos_plantel(id) ON DELETE CASCADE,
    autoridad_expide      VARCHAR(200),
    folio_dictamen        VARCHAR(50),
    giro_autorizado       VARCHAR(200)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dictamenes_uso_suelo CASCADE');
    }
};
