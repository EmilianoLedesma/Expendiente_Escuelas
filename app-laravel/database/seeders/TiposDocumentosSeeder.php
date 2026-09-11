<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de los 6 documentos de Paso 2.2 (COMPENDIO_MAESTRO §Paso 2.2) —
 * 7 filas porque acta_nacimiento/escritura_poder_facultades son dos
 * catálogos mutuamente excluyentes por tipo_persona, no dos documentos
 * simultáneos. requiere_pdf usa el default TRUE de la tabla — los 7 son PDF.
 */
class TiposDocumentosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tipos_documentos')->insertOrIgnore([
            ['clave' => 'ine', 'nombre' => 'Credencial de elector (INE)', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'acta_nacimiento', 'nombre' => 'Acta de nacimiento', 'aplica_persona' => 'fisica', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'escritura_poder_facultades', 'nombre' => 'Escritura o poder notarial de facultades del representante legal', 'aplica_persona' => 'moral', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'escritura_inmueble', 'nombre' => 'Escritura del bien inmueble', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            ['clave' => 'dictamen_uso_suelo', 'nombre' => 'Dictamen de Uso de Suelo vigente', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => 30],
            ['clave' => 'constancia_seguridad_estructural', 'nombre' => 'Constancia de Seguridad Estructural y de Ocupación', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            ['clave' => 'formato_solicitud', 'nombre' => 'Formato de Solicitud', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
        ]);
    }
}
