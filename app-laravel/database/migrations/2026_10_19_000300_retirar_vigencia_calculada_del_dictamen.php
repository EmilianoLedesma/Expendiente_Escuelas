<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retira la caducidad calculada (fecha de emisión + 30 días) del Dictamen de
 * Uso de Suelo. Esos 30 días eran una antigüedad administrativa operativa
 * confirmada por el dueño, sin fundamento en los documentos normativos fuente
 * (COMPENDIO_MAESTRO), y el sistema la trataba como vigencia jurídica. Desde
 * 2026-10-19 la vigencia del dictamen es la que declara el documento
 * (dictamenes_uso_suelo.fecha_vencimiento_documento) y la antigüedad queda como
 * pregunta abierta para SEDEQ. down() restaura el cálculo anterior.
 */
return new class extends Migration
{
    private const CLAVE = 'dictamen_uso_suelo';

    public function up(): void
    {
        DB::table('tipos_documentos')->where('clave', self::CLAVE)->update(['vigencia_max_dias' => null, 'updated_at' => now()]);
        DB::table('documentos_plantel')
            ->whereIn('tipo_documento_id', DB::table('tipos_documentos')->where('clave', self::CLAVE)->select('id'))
            ->update(['fecha_vigencia' => null]);
    }

    public function down(): void
    {
        DB::table('tipos_documentos')->where('clave', self::CLAVE)->update(['vigencia_max_dias' => 30, 'updated_at' => now()]);
        DB::table('documentos_plantel')
            ->whereIn('tipo_documento_id', DB::table('tipos_documentos')->where('clave', self::CLAVE)->select('id'))
            ->whereNotNull('fecha_emision')
            ->update(['fecha_vigencia' => DB::raw("fecha_emision + INTERVAL '30 days'")]);
    }
};
