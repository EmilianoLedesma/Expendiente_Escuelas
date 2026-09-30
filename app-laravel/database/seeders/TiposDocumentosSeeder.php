<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de documentos. 18 filas: 16 desde WS-5b, más 2 de ADR-007:
 *  - Paso 2.2 (ámbito plantel/escuela): los 7 originales (COMPENDIO_MAESTRO
 *    §Paso 2.2) más los que WS-5a agregó contra los Requisitos (Apéndice B.2
 *    del brief de remediación), menos el Formato de Solicitud y el recibo
 *    del plantel.
 *  - Paso 2.4 (ámbito escuela_nivel, WS-5b): el Formato de Solicitud (pasó
 *    de escuela a nivel), el recibo de pago de derechos (reemplaza al
 *    marcador recibo_pago_derechos_plantel de WS-5a) y los documentos
 *    propios de un nivel (nivel_educativo_id no nulo): acervo bibliográfico
 *    de Primaria y de Secundaria (cantidades normativas distintas, una
 *    clave por nivel) e inventario de laboratorio de Secundaria.
 *
 * acta_nacimiento es 'ambas': el representante de una persona moral también
 * presenta la suya (Requisitos Básica §4), además de
 * escritura_poder_facultades/acta_constitutiva.
 *
 * Orden de inserción = orden de renderizado del checklist
 * (clavesAplicables() de DocumentosCompletos/DocumentosNivelCompletos ordena
 * por id) — las filas nuevas van SIEMPRE al final, nunca intercaladas.
 */
class TiposDocumentosSeeder extends Seeder
{
    public function run(): void
    {
        // Las filas por nivel referencian niveles_educativos por clave; sin él
        // quedarían con nivel_educativo_id NULL y aplicarían a todo nivel.
        // CatalogoMinimoSeeder es idempotente (insertOrIgnore).
        (new CatalogoMinimoSeeder)->run();

        DB::table('tipos_documentos')->insertOrIgnore([
            ['clave' => 'ine', 'nombre' => 'Credencial de elector (INE)', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'acta_nacimiento', 'nombre' => 'Acta de nacimiento', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'escritura_poder_facultades', 'nombre' => 'Escritura o poder notarial de facultades del representante legal', 'aplica_persona' => 'moral', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'escritura_inmueble', 'nombre' => 'Escritura del bien inmueble', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            ['clave' => 'dictamen_uso_suelo', 'nombre' => 'Dictamen de Uso de Suelo vigente', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => 30],
            ['clave' => 'constancia_seguridad_estructural', 'nombre' => 'Constancia de Seguridad Estructural y de Ocupación', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            ['clave' => 'formato_solicitud', 'nombre' => 'Formato de Solicitud', 'aplica_persona' => 'ambas', 'ambito' => 'escuela_nivel', 'vigencia_max_dias' => null],
            ['clave' => 'acta_constitutiva', 'nombre' => 'Acta constitutiva', 'aplica_persona' => 'moral', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'poder_gestor', 'nombre' => 'Poder general para actos de administración (gestor)', 'aplica_persona' => 'fisica_con_gestor', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'visto_bueno_proteccion_civil', 'nombre' => 'Visto Bueno / Dictamen de Protección Civil', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            ['clave' => 'plano_inmueble', 'nombre' => 'Plano o croquis del inmueble', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            ['clave' => 'certificado_numero_oficial', 'nombre' => 'Certificado de número oficial', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => null],
            // ADR-007 (owner decision): sources for the CURP and RFC checks.
            // CURP: of the person who acts (titular, gestor or representative, like the INE).
            // Situación fiscal: of the taxpayer (titular, or the persona moral).
            ['clave' => 'constancia_curp', 'nombre' => 'Constancia de CURP', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
            ['clave' => 'constancia_situacion_fiscal', 'nombre' => 'Constancia de Situación Fiscal', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null],
        ]);

        // Segundo insert: insertOrIgnore exige las mismas columnas en todas las
        // filas, y solo estas llevan nivel_educativo_id.
        $nivel = fn (string $clave) => DB::table('niveles_educativos')->where('clave', $clave)->value('id');

        DB::table('tipos_documentos')->insertOrIgnore([
            ['clave' => 'recibo_pago_derechos', 'nombre' => 'Recibo de pago de derechos', 'aplica_persona' => 'ambas', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => null, 'vigencia_max_dias' => null],
            ['clave' => 'acervo_bibliografico_primaria', 'nombre' => 'Relación del acervo bibliográfico', 'aplica_persona' => 'ambas', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => $nivel('primaria'), 'vigencia_max_dias' => null],
            ['clave' => 'acervo_bibliografico_secundaria', 'nombre' => 'Relación del acervo bibliográfico', 'aplica_persona' => 'ambas', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => $nivel('secundaria'), 'vigencia_max_dias' => null],
            ['clave' => 'inventario_laboratorio', 'nombre' => 'Inventario del laboratorio', 'aplica_persona' => 'ambas', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => $nivel('secundaria'), 'vigencia_max_dias' => null],
        ]);
    }
}
