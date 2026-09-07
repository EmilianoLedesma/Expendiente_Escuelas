<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReglasValidacionSeeder extends Seeder
{
    /**
     * Reglas de superficie y personal — COMPENDIO_MAESTRO §5 (Inicial) y
     * §5.2 (Preescolar/Primaria/Secundaria, Acuerdos 357/254/255).
     * Mobiliario y el detalle granular de puertas/escaleras/pasillos/
     * sanitarios-por-rango quedan fuera de este seeder — ver plan.
     *
     * `clave` es UNIQUE en el DDL, por lo que insertOrIgnore basta para
     * idempotencia — ya no se necesita el delete-then-insert-por-nivel
     * de la versión anterior de este seeder.
     *
     * NOTA (umbral 61, no 60): COMPENDIO §5.2 (texto de los Acuerdos
     * Secretariales 357/254/255) y la tabla resumen dicen consistentemente
     * "más de 60 alumnos" (>60) para los tres niveles — condicion_min = 61.
     * La redacción más temprana de §5.1 ("60 alumnos o más" para Preescolar)
     * es la que está en conflicto, y queda superada por §5.2 al ser la
     * fuente normativa más específica (los Acuerdos Secretariales en sí).
     * Decisión confirmada 2026-09-04 (docs/progress.md Decisions Log) — no
     * cambiar a 60 sin nueva instrucción.
     *
     * NOTA (umbral vs. proporcional): §5.1 distingue Preescolar ("solo si
     * la instalación tiene capacidad de 60 alumnos o más" — umbral, un
     * docente) de Primaria ("por cada 60 alumnos o más en la escuela...
     * se puede requerir más de uno" — proporcional). Preescolar usa
     * personal_umbral; Primaria usa personal_proporcional con
     * condicion_min = 61 como puerta de entrada y valor_numerico = 60
     * como la razón alumnos/docente.
     */
    public function run(): void
    {
        $niveles = DB::table('niveles_educativos')->pluck('id', 'clave');

        $fuentes = [
            'inicial' => 'REQUISITOS_DE_EDUCACIÓN_INICIAL.docx',
            'preescolar' => 'Acuerdo Secretarial 357 (Preescolar)',
            'primaria' => 'Acuerdo Secretarial 254 (Primaria)',
            'secundaria' => 'Acuerdo Secretarial 255 (Secundaria)',
        ];

        $porNivel = [
            'inicial' => [
                ['clave' => 'inicial.superficie.aula_lactantes', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'minimo_fijo', 'ambito' => 'sala', 'concepto' => 'Superficie mínima de aula (Lactantes)', 'condicion_max' => 10, 'valor_numerico' => 25, 'unidad' => 'm²/sala'],
                ['clave' => 'inicial.superficie.aula_maternales', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'minimo_fijo', 'ambito' => 'sala', 'concepto' => 'Superficie mínima de aula (Maternales)', 'condicion_max' => 15, 'valor_numerico' => 25, 'unidad' => 'm²/sala'],
                ['clave' => 'inicial.superficie.area_recreativa', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'concepto' => 'Área recreativa mínima', 'valor_numerico' => 1, 'unidad' => 'm²/alumno'],
                ['clave' => 'inicial.superficie.sala_usos_multiples', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'concepto' => 'Sala de usos múltiples', 'valor_numerico' => 1.2, 'unidad' => 'm²/niño'],
                ['clave' => 'inicial.superficie.sanitarios', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'concepto' => 'Sanitarios / control de esfínter', 'valor_numerico' => 0.80, 'unidad' => 'm²/infante'],
                ['clave' => 'inicial.personal.responsable_sala', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_por_espacio', 'ambito' => 'sala', 'concepto' => 'Responsable de sala (fijo por sala existente)', 'valor_numerico' => 1, 'unidad' => 'responsable/sala'],
                ['clave' => 'inicial.personal.asistente_lactantes', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_proporcional', 'ambito' => 'sala', 'concepto' => 'Asistente educativo en salas de lactantes', 'valor_numerico' => 5, 'unidad' => 'alumnos/asistente'],
                ['clave' => 'inicial.personal.asistente_maternales', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_proporcional', 'ambito' => 'sala', 'concepto' => 'Asistente educativo en salas de maternales', 'valor_numerico' => 10, 'unidad' => 'alumnos/asistente'],
                ['clave' => 'inicial.personal.director_tecnico', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_obligatorio', 'ambito' => 'plantel', 'concepto' => 'Director Técnico obligatorio por plantel', 'valor_numerico' => 1, 'unidad' => 'director/plantel'],
            ],
            'preescolar' => [
                ['clave' => 'preescolar.superficie.construida_total', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'concepto' => 'Superficie construida total', 'valor_numerico' => 1.00, 'unidad' => 'm²/educando'],
                ['clave' => 'preescolar.superficie.aula', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'aula', 'concepto' => 'Superficie de aula (por educando)', 'valor_numerico' => 1.00, 'unidad' => 'm²/educando'],
                ['clave' => 'preescolar.superficie.espacio_maestro', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'adicional_fijo', 'ambito' => 'aula', 'concepto' => 'Espacio adicional del maestro en aula', 'valor_numerico' => 2, 'unidad' => 'm² fijo'],
                ['clave' => 'preescolar.superficie.area_recreacion', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'concepto' => 'Área de recreación', 'valor_numerico' => 1.25, 'unidad' => 'm²/educando'],
                ['clave' => 'preescolar.superficie.aula_usos_multiples', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'factor', 'ambito' => 'aula', 'concepto' => 'Aula de usos múltiples (factor sobre aula mayor)', 'valor_numerico' => 1.5, 'unidad' => 'factor'],
                ['clave' => 'preescolar.personal.educacion_fisica', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_umbral', 'ambito' => 'escuela', 'concepto' => 'Docente de Educación Física obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'docente'],
            ],
            'primaria' => [
                ['clave' => 'primaria.superficie.predio_total', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'predio', 'concepto' => 'Superficie total del predio', 'valor_numerico' => 2.50, 'unidad' => 'm²/alumno'],
                ['clave' => 'primaria.superficie.aulas', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'aula', 'concepto' => 'Superficie de aulas', 'valor_numerico' => 0.90, 'unidad' => 'm²/alumno'],
                ['clave' => 'primaria.superficie.altura_aulas', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'minimo_fijo', 'ambito' => 'aula', 'concepto' => 'Altura de aulas', 'valor_numerico' => 2.70, 'unidad' => 'm fijo'],
                ['clave' => 'primaria.superficie.acervo_bibliografico', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_grado', 'ambito' => 'escuela', 'concepto' => 'Acervo bibliográfico mínimo', 'valor_numerico' => 50, 'unidad' => 'títulos/grado'],
                ['clave' => 'primaria.personal.educacion_fisica', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_proporcional', 'ambito' => 'escuela', 'concepto' => 'Docente de Educación Física obligatorio', 'condicion_min' => 61, 'valor_numerico' => 60, 'unidad' => 'alumnos/docente'],
            ],
            'secundaria' => [
                ['clave' => 'secundaria.superficie.predio_total', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'predio', 'concepto' => 'Superficie total del predio', 'valor_numerico' => 2.50, 'unidad' => 'm²/alumno'],
                ['clave' => 'secundaria.superficie.aulas', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'aula', 'concepto' => 'Superficie de aulas', 'valor_numerico' => 0.90, 'unidad' => 'm²/alumno'],
                ['clave' => 'secundaria.superficie.area_recreacion', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'concepto' => 'Área de recreación', 'valor_numerico' => 1.25, 'unidad' => 'm²/alumno'],
                ['clave' => 'secundaria.superficie.areas_recreativas_minimas', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'minimo_fijo', 'ambito' => 'plantel', 'concepto' => 'Áreas recreativas/deportivas mínimas', 'valor_numerico' => 200, 'unidad' => 'm² fijo'],
                ['clave' => 'secundaria.superficie.acervo_bibliografico', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'minimo_fijo', 'ambito' => 'escuela', 'concepto' => 'Acervo bibliográfico mínimo total', 'valor_numerico' => 300, 'unidad' => 'títulos totales'],
                ['clave' => 'secundaria.personal.educacion_fisica', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_umbral', 'ambito' => 'escuela', 'concepto' => 'Docente de Educación Física obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'docente'],
                ['clave' => 'secundaria.personal.trabajador_social', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_umbral', 'ambito' => 'escuela', 'concepto' => 'Trabajador social obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'trabajador social'],
                ['clave' => 'secundaria.personal.prefecto', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_umbral', 'ambito' => 'escuela', 'concepto' => 'Prefecto obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'prefecto'],
            ],
        ];

        foreach ($porNivel as $clave => $reglas) {
            $nivelId = $niveles[$clave];

            $rows = array_map(function (array $regla) use ($nivelId, $fuentes, $clave) {
                return array_merge([
                    'nivel_educativo_id' => $nivelId,
                    'cargo_puesto_id' => null,
                    'condicion_min' => null,
                    'condicion_max' => null,
                    'fuente' => $fuentes[$clave],
                ], $regla);
            }, $reglas);

            DB::table('reglas_validacion')->insertOrIgnore($rows);
        }
    }
}
