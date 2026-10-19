<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Datos propios de cada figura de ocupación legal (propietario de la escritura,
 * inicio y vigencia indeterminada del contrato, facultades del arrendador o
 * comodante, ratificación notarial y metadatos de "otro instrumento"). Todas
 * las columnas son nuevas y nullable: las acreditaciones ya capturadas
 * conservan NULL.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const COLUMNAS = [
        'propietario_nombre' => 'VARCHAR(200)',
        'fecha_inicio_vigencia' => 'DATE',
        'vigencia_indeterminada' => 'BOOLEAN',
        'arrendador_es_propietario' => 'BOOLEAN',
        'facultades_referencia' => 'VARCHAR(200)',
        'ratificacion_fecha' => 'DATE',
        'ratificacion_notario_nombre' => 'VARCHAR(150)',
        'ratificacion_notaria_numero' => 'VARCHAR(20)',
        'otro_referencia' => 'VARCHAR(100)',
        'otro_titular' => 'VARCHAR(200)',
        'otro_fecha_expedicion' => 'DATE',
        'otro_autoridad' => 'VARCHAR(200)',
        'otro_derecho' => 'VARCHAR(200)',
    ];

    public function up(): void
    {
        foreach (self::COLUMNAS as $columna => $tipo) {
            DB::statement("ALTER TABLE acreditaciones_ocupacion_legal ADD COLUMN {$columna} {$tipo}");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::COLUMNAS) as $columna) {
            DB::statement("ALTER TABLE acreditaciones_ocupacion_legal DROP COLUMN {$columna}");
        }
    }
};
