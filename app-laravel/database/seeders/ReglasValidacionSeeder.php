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
     * reglas_validacion no tiene UNIQUE en el DDL: se limpia por nivel
     * antes de insertar para mantener la operación idempotente.
     *
     * NOTA: condicion_min = 61 ("más de 60 alumnos") sigue COMPENDIO §5.2
     * (Acuerdos Secretariales 357/254/255), preferido sobre la redacción
     * más temprana de §5.1 ("≥60 alumnos" / "60 o más") que es internamente
     * inconsistente con §5.2. Decisión confirmada 2026-09-04 — no cambiar
     * a 60 sin nueva instrucción.
     */
    public function run(): void
    {
        $niveles = DB::table('niveles_educativos')->pluck('id', 'clave');

        $porNivel = [
            'inicial' => [
                ['tipo_regla' => 'superficie', 'concepto' => 'Superficie mínima de aula (Lactantes)', 'condicion_max' => 10, 'valor_numerico' => 25, 'unidad' => 'm²/aula'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Superficie mínima de aula (Maternales)', 'condicion_max' => 15, 'valor_numerico' => 25, 'unidad' => 'm²/aula'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Área recreativa mínima', 'valor_numerico' => 1, 'unidad' => 'm²/alumno'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Sala de usos múltiples', 'valor_numerico' => 1.2, 'unidad' => 'm²/niño'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Sanitarios / control de esfínter', 'valor_numerico' => 0.80, 'unidad' => 'm²/infante'],
                ['tipo_regla' => 'personal', 'concepto' => 'Responsable de sala (fijo por sala existente)', 'valor_numerico' => 1, 'unidad' => 'responsable/sala'],
                ['tipo_regla' => 'personal', 'concepto' => 'Asistente educativo en salas de lactantes', 'valor_numerico' => 5, 'unidad' => 'alumnos/asistente'],
                ['tipo_regla' => 'personal', 'concepto' => 'Asistente educativo en salas de maternales', 'valor_numerico' => 10, 'unidad' => 'alumnos/asistente'],
                ['tipo_regla' => 'personal', 'concepto' => 'Director Técnico obligatorio por plantel', 'valor_numerico' => 1, 'unidad' => 'director/plantel'],
            ],
            'preescolar' => [
                ['tipo_regla' => 'superficie', 'concepto' => 'Superficie construida total', 'valor_numerico' => 1.00, 'unidad' => 'm²/educando'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Superficie de aula (por educando)', 'valor_numerico' => 1.00, 'unidad' => 'm²/educando'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Espacio adicional del maestro en aula', 'valor_numerico' => 2, 'unidad' => 'm² fijo'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Área de recreación', 'valor_numerico' => 1.25, 'unidad' => 'm²/educando'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Aula de usos múltiples (factor sobre aula mayor)', 'valor_numerico' => 1.5, 'unidad' => 'factor'],
                ['tipo_regla' => 'personal', 'concepto' => 'Docente de Educación Física obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'docente'],
            ],
            'primaria' => [
                ['tipo_regla' => 'superficie', 'concepto' => 'Superficie total del predio', 'valor_numerico' => 2.50, 'unidad' => 'm²/alumno'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Superficie de aulas', 'valor_numerico' => 0.90, 'unidad' => 'm²/alumno'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Altura de aulas', 'valor_numerico' => 2.70, 'unidad' => 'm fijo'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Acervo bibliográfico mínimo', 'valor_numerico' => 50, 'unidad' => 'títulos/grado'],
                ['tipo_regla' => 'personal', 'concepto' => 'Docente de Educación Física obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'docente'],
            ],
            'secundaria' => [
                ['tipo_regla' => 'superficie', 'concepto' => 'Superficie total del predio', 'valor_numerico' => 2.50, 'unidad' => 'm²/alumno'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Superficie de aulas', 'valor_numerico' => 0.90, 'unidad' => 'm²/alumno'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Área de recreación', 'valor_numerico' => 1.25, 'unidad' => 'm²/alumno'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Áreas recreativas/deportivas mínimas', 'valor_numerico' => 200, 'unidad' => 'm² fijo'],
                ['tipo_regla' => 'superficie', 'concepto' => 'Acervo bibliográfico mínimo total', 'valor_numerico' => 300, 'unidad' => 'títulos totales'],
                ['tipo_regla' => 'personal', 'concepto' => 'Docente de Educación Física obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'docente'],
                ['tipo_regla' => 'personal', 'concepto' => 'Trabajador social obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'trabajador social'],
                ['tipo_regla' => 'personal', 'concepto' => 'Prefecto obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'prefecto'],
            ],
        ];

        $fuentes = [
            'inicial' => 'REQUISITOS_DE_EDUCACIÓN_INICIAL.docx',
            'preescolar' => 'Acuerdo Secretarial 357 (Preescolar)',
            'primaria' => 'Acuerdo Secretarial 254 (Primaria)',
            'secundaria' => 'Acuerdo Secretarial 255 (Secundaria)',
        ];

        foreach ($porNivel as $clave => $reglas) {
            $nivelId = $niveles[$clave];

            // No hay UNIQUE en el DDL para esta tabla — se limpia por nivel
            // antes de insertar para que el seeder sea idempotente.
            DB::table('reglas_validacion')->where('nivel_educativo_id', $nivelId)->delete();

            $rows = array_map(function (array $regla) use ($nivelId, $fuentes, $clave) {
                return array_merge([
                    'nivel_educativo_id' => $nivelId,
                    'condicion_min' => null,
                    'condicion_max' => null,
                    'fuente' => $fuentes[$clave],
                ], $regla);
            }, $reglas);

            DB::table('reglas_validacion')->insert($rows);
        }
    }
}
