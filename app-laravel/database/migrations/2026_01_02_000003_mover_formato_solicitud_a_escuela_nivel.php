<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * WS-5b: el Formato de Solicitud deja Paso 2.2 y pasa a ser por nivel
 * (Paso 2.4). TiposDocumentosSeeder usa insertOrIgnore y nunca actualiza
 * filas existentes, así que una BD ya sembrada conservaría ámbito 'escuela'
 * (mismo caso que acta_nacimiento en 000002).
 *
 * En una BD ya sembrada también agrega las 4 filas por nivel (copia fija, no
 * el seeder vivo: esta migración no debe cambiar si el seeder cambia): sin
 * ellas 2.4 se daría por completo solo con el Formato. En una BD nueva el
 * catálogo está vacío aquí (no-op) y TiposDocumentosSeeder las agrega después
 * en el orden correcto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('tipos_documentos')->where('clave', 'formato_solicitud')->doesntExist()) {
            return;
        }

        DB::table('tipos_documentos')->where('clave', 'formato_solicitud')->update(['ambito' => 'escuela_nivel', 'updated_at' => now()]);

        $nivel = function (string $clave): int {
            $id = DB::table('niveles_educativos')->where('clave', $clave)->value('id');

            // Un NULL haría que el documento aplicara a todo nivel: mejor fallar.
            return $id ?? throw new RuntimeException("niveles_educativos no tiene '{$clave}' — corre CatalogoMinimoSeeder.");
        };

        DB::table('tipos_documentos')->insertOrIgnore([
            ['clave' => 'recibo_pago_derechos', 'nombre' => 'Recibo de pago de derechos', 'aplica_persona' => 'ambas', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => null, 'vigencia_max_dias' => null],
            ['clave' => 'acervo_bibliografico_primaria', 'nombre' => 'Relación del acervo bibliográfico', 'aplica_persona' => 'ambas', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => $nivel('primaria'), 'vigencia_max_dias' => null],
            ['clave' => 'acervo_bibliografico_secundaria', 'nombre' => 'Relación del acervo bibliográfico', 'aplica_persona' => 'ambas', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => $nivel('secundaria'), 'vigencia_max_dias' => null],
            ['clave' => 'inventario_laboratorio', 'nombre' => 'Inventario del laboratorio', 'aplica_persona' => 'ambas', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => $nivel('secundaria'), 'vigencia_max_dias' => null],
        ]);
    }

    public function down(): void
    {
        // Solo revierte el ámbito: las filas por nivel se quedan porque
        // documentos_escuela_nivel puede ya referenciarlas.
        DB::table('tipos_documentos')->where('clave', 'formato_solicitud')->update(['ambito' => 'escuela', 'updated_at' => now()]);
    }
};
