<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReglasValidacionSeeder extends Seeder
{
    /**
     * Reglas de superficie, personal e infraestructura — COMPENDIO_MAESTRO §5
     * (Inicial) y §5.2 (Preescolar/Primaria/Secundaria, Acuerdos 357/254/255).
     * Mobiliario y el detalle granular de puertas/escaleras/pasillos/
     * sanitarios-por-rango quedan fuera de este seeder — ver plan.
     *
     * `clave` es UNIQUE en el DDL; se hace upsert sobre esa columna para
     * que una corrección de valores en una fila ya sembrada se aplique en
     * un re-run en vez de quedar ignorada por insertOrIgnore (WS-3.4).
     *
     * Depende de que CargosPuestosSeeder ya haya corrido (DatabaseSeeder lo
     * llama antes) — cada fila `personal` con un cargo real conocido se
     * enlaza a `cargos_puestos` por (nivel, nombre del cargo), nunca por id
     * fijo. Si esa dependencia no corrió, o el cargo nombrado no existe
     * para ese nivel, se falla con RuntimeException en vez de sembrar
     * cargo_puesto_id = null en silencio. La única excepción documentada es
     * `secundaria.personal.educacion_fisica`: en Secundaria, Educación
     * Física no es un cargo propio, es una asignatura que imparte el cargo
     * "Docente Titular" (`requiere_asignatura = true`, COMPENDIO §5.1
     * líneas 487–489). `reglas_validacion` no tiene columna `asignatura_id`,
     * así que enlazarla a un `cargo_puesto_id` real requiere una decisión de
     * esquema o de Motor, no solo de datos — ver
     * docs/decisions/PENDIENTE-perfiles-trabajador-social-prefecto.md,
     * sección "Hueco relacionado: secundaria.personal.educacion_fisica sin
     * cargo_puesto_id". Esa fila se deja sin `cargo` a propósito
     * (cargo_puesto_id queda null).
     *
     * NOTA (umbral vs. proporcional): §5.1 distingue Preescolar ("solo si
     * la instalación tiene capacidad de 60 alumnos o más" — umbral, un
     * docente) de Primaria ("por cada 60 alumnos o más en la escuela...
     * se puede requerir más de uno" — proporcional). Preescolar usa
     * personal_umbral. Primaria usa personal_proporcional SIN
     * condicion_min: el umbral queda implícito en la división entera
     * (floor(alumnos / 60), redondeo = 'abajo'), no como una puerta
     * separada — ver docs/reports/2026-09-07-reglas-validacion-schema.md
     * (el defecto que se corrige ahí: una puerta condicion_min = 61 junto
     * con la fórmula proporcional producía un salto de 0 a 2 docentes
     * entre 60 y 61 alumnos).
     *
     * NOTA (redondeo): COMPENDIO línea 422 especifica explícitamente
     * "redondeo hacia arriba" para los dos asistentes de Inicial (1 por
     * cada 5 lactantes, 1 por cada 10 maternales) — son personal_proporcional
     * igual que la regla de Educación Física de Primaria, pero con
     * semántica de redondeo OPUESTA (arriba, no abajo). Nada distinguía
     * esto antes de la columna `redondeo` — ver el reporte, sección de
     * auditoría de redondeo, para el detalle fila por fila.
     *
     * NOTA (umbral 60 vs. 61, PROVISIONAL): el valor de condicion_min = 61
     * en las reglas *_umbral de abajo (Preescolar Educación Física,
     * Secundaria Educación Física/Trabajador Social/Prefecto) es
     * provisional y sigue abierto — ver
     * docs/decisions/PENDIENTE-umbral-educacion-fisica.md. No cambiar sin
     * resolver ese documento.
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
                ['clave' => 'inicial.superficie.aula_lactantes', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'minimo_fijo', 'ambito' => 'sala', 'redondeo' => 'na', 'concepto' => 'Superficie mínima de aula (Lactantes)', 'condicion_max' => 10, 'valor_numerico' => 25, 'unidad' => 'm²/sala'],
                ['clave' => 'inicial.superficie.aula_maternales', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'minimo_fijo', 'ambito' => 'sala', 'redondeo' => 'na', 'concepto' => 'Superficie mínima de aula (Maternales)', 'condicion_max' => 15, 'valor_numerico' => 25, 'unidad' => 'm²/sala'],
                ['clave' => 'inicial.superficie.area_recreativa', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'redondeo' => 'na', 'concepto' => 'Área recreativa mínima', 'valor_numerico' => 1, 'unidad' => 'm²/alumno'],
                ['clave' => 'inicial.superficie.sala_usos_multiples', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'redondeo' => 'na', 'concepto' => 'Sala de usos múltiples', 'valor_numerico' => 1.2, 'unidad' => 'm²/niño'],
                ['clave' => 'inicial.superficie.sanitarios', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'redondeo' => 'na', 'concepto' => 'Sanitarios / control de esfínter', 'valor_numerico' => 0.80, 'unidad' => 'm²/infante'],
                ['clave' => 'inicial.personal.responsable_sala', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_por_espacio', 'ambito' => 'sala', 'redondeo' => 'na', 'concepto' => 'Responsable de sala (fijo por sala existente)', 'valor_numerico' => 1, 'unidad' => 'responsable/sala', 'cargo' => 'Responsable de Sala'],
                ['clave' => 'inicial.personal.asistente_lactantes', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_proporcional', 'ambito' => 'sala', 'redondeo' => 'arriba', 'concepto' => 'Asistente educativo en salas de lactantes', 'valor_numerico' => 5, 'unidad' => 'alumnos/asistente', 'cargo' => 'Asistente Educativo'],
                ['clave' => 'inicial.personal.asistente_maternales', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_proporcional', 'ambito' => 'sala', 'redondeo' => 'arriba', 'concepto' => 'Asistente educativo en salas de maternales', 'valor_numerico' => 10, 'unidad' => 'alumnos/asistente', 'cargo' => 'Asistente Educativo'],
                ['clave' => 'inicial.personal.director_tecnico', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_obligatorio', 'ambito' => 'plantel', 'redondeo' => 'na', 'concepto' => 'Director Técnico obligatorio por plantel', 'valor_numerico' => 1, 'unidad' => 'director/plantel', 'cargo' => 'Director Técnico'],
                // Profesiograma Inicial (SEDEQ, ciclo 2023-2024), no COMPENDIO —
                // ver Apéndice B.4 de AGENT_BRIEF_remediacion-auditoria-2026-09-23.md.
                ['clave' => 'inicial.personal.responsable_filtro', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_obligatorio', 'ambito' => 'plantel', 'redondeo' => 'na', 'concepto' => 'Responsable de Filtro y Fomento a la Salud (Obligatorio)', 'valor_numerico' => 1, 'unidad' => 'responsable/plantel', 'cargo' => 'Responsable de Filtro y Fomento a la Salud', 'fuente' => 'Profesiograma Inicial (SEDEQ, ciclo 2023-2024)'],
            ],
            'preescolar' => [
                ['clave' => 'preescolar.superficie.construida_total', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'redondeo' => 'na', 'concepto' => 'Superficie construida total', 'valor_numerico' => 1.00, 'unidad' => 'm²/educando'],
                ['clave' => 'preescolar.superficie.aula', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'aula', 'redondeo' => 'na', 'concepto' => 'Superficie de aula (por educando)', 'valor_numerico' => 1.00, 'unidad' => 'm²/educando'],
                ['clave' => 'preescolar.superficie.espacio_maestro', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'adicional_fijo', 'ambito' => 'aula', 'redondeo' => 'na', 'concepto' => 'Espacio adicional del maestro en aula', 'valor_numerico' => 2, 'unidad' => 'm² fijo'],
                ['clave' => 'preescolar.superficie.area_recreacion', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'redondeo' => 'na', 'concepto' => 'Área de recreación', 'valor_numerico' => 1.25, 'unidad' => 'm²/educando'],
                ['clave' => 'preescolar.superficie.aula_usos_multiples', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'factor', 'ambito' => 'aula', 'redondeo' => 'na', 'concepto' => 'Aula de usos múltiples (factor sobre aula mayor)', 'valor_numerico' => 1.5, 'unidad' => 'factor'],
                ['clave' => 'preescolar.personal.educacion_fisica', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_umbral', 'ambito' => 'escuela', 'redondeo' => 'na', 'concepto' => 'Docente de Educación Física obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'docente', 'cargo' => 'Docente de Educación Física'],
                ['clave' => 'preescolar.personal.director_tecnico', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_obligatorio', 'ambito' => 'escuela', 'redondeo' => 'na', 'concepto' => 'Director Técnico obligatorio por escuela', 'valor_numerico' => 1, 'unidad' => 'director/escuela', 'cargo' => 'Director Técnico', 'fuente' => 'Profesiograma Preescolar (SEDEQ, ciclo 2023-2024)'],
            ],
            'primaria' => [
                ['clave' => 'primaria.superficie.predio_total', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'predio', 'redondeo' => 'na', 'concepto' => 'Superficie total del predio', 'valor_numerico' => 2.50, 'unidad' => 'm²/alumno'],
                ['clave' => 'primaria.superficie.aulas', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'aula', 'redondeo' => 'na', 'concepto' => 'Superficie de aulas', 'valor_numerico' => 0.90, 'unidad' => 'm²/alumno'],
                ['clave' => 'primaria.infraestructura.altura_aulas', 'tipo_regla' => 'infraestructura', 'tipo_calculo' => 'minimo_fijo', 'ambito' => 'aula', 'redondeo' => 'na', 'concepto' => 'Altura de aulas', 'valor_numerico' => 2.70, 'unidad' => 'm fijo'],
                ['clave' => 'primaria.infraestructura.acervo_bibliografico', 'tipo_regla' => 'infraestructura', 'tipo_calculo' => 'ratio_por_grado', 'ambito' => 'escuela', 'redondeo' => 'na', 'concepto' => 'Acervo bibliográfico mínimo', 'valor_numerico' => 50, 'unidad' => 'títulos/grado'],
                ['clave' => 'primaria.personal.educacion_fisica', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_proporcional', 'ambito' => 'escuela', 'redondeo' => 'abajo', 'concepto' => 'Docente de Educación Física obligatorio', 'valor_numerico' => 60, 'unidad' => 'alumnos/docente', 'cargo' => 'Docente de Educación Física'],
                ['clave' => 'primaria.personal.director_tecnico', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_obligatorio', 'ambito' => 'escuela', 'redondeo' => 'na', 'concepto' => 'Director Técnico obligatorio por escuela', 'valor_numerico' => 1, 'unidad' => 'director/escuela', 'cargo' => 'Director Técnico', 'fuente' => 'Profesiograma Primaria (SEDEQ, ciclo 2023-2024)'],
            ],
            'secundaria' => [
                ['clave' => 'secundaria.superficie.predio_total', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'predio', 'redondeo' => 'na', 'concepto' => 'Superficie total del predio', 'valor_numerico' => 2.50, 'unidad' => 'm²/alumno'],
                ['clave' => 'secundaria.superficie.aulas', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'aula', 'redondeo' => 'na', 'concepto' => 'Superficie de aulas', 'valor_numerico' => 0.90, 'unidad' => 'm²/alumno'],
                ['clave' => 'secundaria.superficie.area_recreacion', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'plantel', 'redondeo' => 'na', 'concepto' => 'Área de recreación', 'valor_numerico' => 1.25, 'unidad' => 'm²/alumno'],
                ['clave' => 'secundaria.superficie.areas_recreativas_minimas', 'tipo_regla' => 'superficie', 'tipo_calculo' => 'minimo_fijo', 'ambito' => 'plantel', 'redondeo' => 'na', 'concepto' => 'Áreas recreativas/deportivas mínimas', 'valor_numerico' => 200, 'unidad' => 'm² fijo'],
                ['clave' => 'secundaria.infraestructura.acervo_bibliografico', 'tipo_regla' => 'infraestructura', 'tipo_calculo' => 'minimo_fijo', 'ambito' => 'escuela', 'redondeo' => 'na', 'concepto' => 'Acervo bibliográfico mínimo total', 'valor_numerico' => 300, 'unidad' => 'títulos totales'],
                // secundaria.personal.educacion_fisica: NO tiene 'cargo' a propósito.
                // En Secundaria, Educación Física es una asignatura del cargo
                // "Docente Titular" (requiere_asignatura = true), no un cargo propio
                // como en Preescolar/Primaria — reglas_validacion no tiene columna
                // asignatura_id, así que enlazarla requiere una decisión de esquema
                // o de Motor. Ver docs/decisions/
                // PENDIENTE-perfiles-trabajador-social-prefecto.md, sección "Hueco
                // relacionado". cargo_puesto_id queda null; documentado, no un
                // lookup fallido (WS-3.4).
                ['clave' => 'secundaria.personal.educacion_fisica', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_umbral', 'ambito' => 'escuela', 'redondeo' => 'na', 'concepto' => 'Docente de Educación Física obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'docente'],
                ['clave' => 'secundaria.personal.trabajador_social', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_umbral', 'ambito' => 'escuela', 'redondeo' => 'na', 'concepto' => 'Trabajador social obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'trabajador social', 'cargo' => 'Trabajador Social'],
                ['clave' => 'secundaria.personal.prefecto', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_umbral', 'ambito' => 'escuela', 'redondeo' => 'na', 'concepto' => 'Prefecto obligatorio', 'condicion_min' => 61, 'valor_numerico' => 1, 'unidad' => 'prefecto', 'cargo' => 'Prefecto'],
                ['clave' => 'secundaria.personal.director_tecnico', 'tipo_regla' => 'personal', 'tipo_calculo' => 'personal_obligatorio', 'ambito' => 'escuela', 'redondeo' => 'na', 'concepto' => 'Director Técnico obligatorio por escuela', 'valor_numerico' => 1, 'unidad' => 'director/escuela', 'cargo' => 'Director Técnico', 'fuente' => 'Profesiograma Secundaria (SEDEQ, ciclo 2023-2024)'],
            ],
        ];

        $cargoId = DB::table('cargos_puestos')
            ->join('niveles_educativos', 'cargos_puestos.nivel_educativo_id', '=', 'niveles_educativos.id')
            ->select('cargos_puestos.id', 'niveles_educativos.clave as nivel', 'cargos_puestos.nombre')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->nivel}:{$row->nombre}" => $row->id]);

        if ($cargoId->isEmpty()) {
            throw new \RuntimeException(
                'ReglasValidacionSeeder requiere que CargosPuestosSeeder haya corrido antes (cargos_puestos está vacío).'
            );
        }

        $allRows = [];

        foreach ($porNivel as $nivelClave => $reglas) {
            $nivelId = $niveles[$nivelClave];

            foreach ($reglas as $regla) {
                $cargoNombre = $regla['cargo'] ?? null;
                unset($regla['cargo']);

                $cargoPuestoId = null;
                if ($cargoNombre !== null) {
                    $key = "{$nivelClave}:{$cargoNombre}";
                    if (! isset($cargoId[$key])) {
                        throw new \RuntimeException(
                            "ReglasValidacionSeeder: cargo '{$cargoNombre}' no encontrado en cargos_puestos para nivel '{$nivelClave}' (regla: {$regla['clave']}). ¿CargosPuestosSeeder corrió antes?"
                        );
                    }
                    $cargoPuestoId = $cargoId[$key];
                }

                $allRows[] = array_merge([
                    'nivel_educativo_id' => $nivelId,
                    'cargo_puesto_id' => $cargoPuestoId,
                    'condicion_min' => null,
                    'condicion_max' => null,
                    'fuente' => $fuentes[$nivelClave],
                ], $regla);
            }
        }

        DB::table('reglas_validacion')->upsert(
            $allRows,
            ['clave'],
            ['nivel_educativo_id', 'tipo_regla', 'tipo_calculo', 'ambito', 'redondeo', 'concepto', 'cargo_puesto_id', 'condicion_min', 'condicion_max', 'valor_numerico', 'unidad', 'fuente']
        );
    }
}
