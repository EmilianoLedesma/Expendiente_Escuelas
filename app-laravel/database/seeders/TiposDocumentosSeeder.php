<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de documentos de Paso 2.2 (plantel/escuela, no escuela_nivel —
 * esos llegan en WS-5b junto con Paso 2.4). 13 filas desde WS-5a (D2/D3a):
 * los 7 originales (COMPENDIO_MAESTRO §Paso 2.2) más 6 que la auditoría del
 * 2026-09-23 encontró faltantes contra los Requisitos reales (Apéndice B.2
 * del brief de remediación).
 *
 * acta_nacimiento pasó de aplica_persona='fisica' a 'ambas': el
 * representante de una persona moral también necesita presentar su propia
 * acta de nacimiento (Requisitos Básica §4: "...Adjuntando identificación
 * oficial vigente ... y acta de nacimiento"), además de
 * escritura_poder_facultades/acta_constitutiva. No sustituye a ninguno de
 * los dos — se agrega.
 *
 * Orden de inserción = orden de renderizado del checklist
 * (DocumentosCompletos::clavesAplicables() ordena por id) — las filas
 * nuevas van SIEMPRE al final, nunca intercaladas, para no reordenar los 7
 * documentos originales que el checklist ya muestra hoy.
 */
class TiposDocumentosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tipos_documentos')->insertOrIgnore([
            ['clave' => 'ine', 'nombre' => 'Credencial de elector (INE)', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'acta_nacimiento', 'nombre' => 'Acta de nacimiento', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'escritura_poder_facultades', 'nombre' => 'Escritura o poder notarial de facultades del representante legal', 'aplica_persona' => 'moral', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'escritura_inmueble', 'nombre' => 'Escritura del bien inmueble', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            ['clave' => 'dictamen_uso_suelo', 'nombre' => 'Dictamen de Uso de Suelo vigente', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => 30],
            ['clave' => 'constancia_seguridad_estructural', 'nombre' => 'Constancia de Seguridad Estructural y de Ocupación', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            ['clave' => 'formato_solicitud', 'nombre' => 'Formato de Solicitud', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'acta_constitutiva', 'nombre' => 'Acta constitutiva', 'aplica_persona' => 'moral', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'poder_gestor', 'nombre' => 'Poder general para actos de administración (gestor)', 'aplica_persona' => 'fisica_con_gestor', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'visto_bueno_proteccion_civil', 'nombre' => 'Visto Bueno / Dictamen de Protección Civil', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            ['clave' => 'plano_inmueble', 'nombre' => 'Plano o croquis del inmueble', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            ['clave' => 'certificado_numero_oficial', 'nombre' => 'Certificado de número oficial', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            ['clave' => 'recibo_pago_derechos_plantel', 'nombre' => 'Recibo de pago de derechos', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
        ]);
    }
}
