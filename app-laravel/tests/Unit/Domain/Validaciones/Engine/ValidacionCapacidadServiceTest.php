<?php

namespace Tests\Unit\Domain\Validaciones\Engine;

use App\Domain\Validaciones\Engine\DatosCapacidadNivel;
use App\Domain\Validaciones\Engine\ValidacionCapacidadService;
use App\Domain\Validaciones\Regla\CalculadoraRequerimiento;
use App\Domain\Validaciones\Regla\ReglaCapacidad;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ReporteValidacion;
use PHPUnit\Framework\TestCase;

/**
 * Rule rows mirror ReglasValidacionSeeder (COMPENDIO §5, §5.2); cargo ids
 * are arbitrary here, the Application layer maps the real ones.
 */
class ValidacionCapacidadServiceTest extends TestCase
{
    private const DIRECTOR = 1;

    private const EDUCACION_FISICA = 2;

    private const RESPONSABLE_SALA = 3;

    private const ASISTENTE = 4;

    private function regla(string $clave, string $tipoCalculo, string $ambito, float $valor, string $redondeo = 'na', ?int $cargo = null, ?float $condicionMin = null): ReglaCapacidad
    {
        return new ReglaCapacidad(
            clave: $clave,
            tipoRegla: explode('.', $clave)[1],
            tipoCalculo: $tipoCalculo,
            ambito: $ambito,
            redondeo: $redondeo,
            concepto: $clave,
            cargoPuestoId: $cargo,
            condicionMin: $condicionMin,
            valorNumerico: $valor,
        );
    }

    /** @param list<ReglaCapacidad> $reglas */
    private function evaluar(array $reglas, DatosCapacidadNivel $datos): ReporteValidacion
    {
        return (new ValidacionCapacidadService(new CalculadoraRequerimiento))->evaluar($reglas, $datos);
    }

    public function test_superficie_de_aulas_cumple_o_no_segun_la_matricula(): void
    {
        $regla = $this->regla('primaria.superficie.aulas', 'ratio_por_alumno', 'aula', 0.90);

        $cumple = $this->evaluar([$regla], new DatosCapacidadNivel(matriculaNivel: 100, numeroAulas: 4, superficieAulas: 90.0));
        $noCumple = $this->evaluar([$regla], new DatosCapacidadNivel(matriculaNivel: 100, numeroAulas: 4, superficieAulas: 89.99));

        $this->assertSame(EstadoResultado::Cumple, $cumple->resultado('primaria.superficie.aulas')?->estado);
        $this->assertSame(EstadoResultado::NoCumple, $noCumple->resultado('primaria.superficie.aulas')?->estado);
        $this->assertEqualsWithDelta(90.0, $noCumple->resultado('primaria.superficie.aulas')?->detalles['requerido'], 0.001);
        $this->assertSame(89.99, $noCumple->resultado('primaria.superficie.aulas')?->detalles['declarado']);
        $this->assertSame('m²', $noCumple->resultado('primaria.superficie.aulas')?->detalles['unidad']);
    }

    public function test_sin_matricula_capturada_no_es_evaluable(): void
    {
        $reporte = $this->evaluar(
            [$this->regla('primaria.superficie.aulas', 'ratio_por_alumno', 'aula', 0.90)],
            new DatosCapacidadNivel(numeroAulas: 4, superficieAulas: 90.0),
        );

        $this->assertSame(EstadoResultado::NoEvaluable, $reporte->resultado('primaria.superficie.aulas')?->estado);
        $this->assertStringContainsString('matrícula', $reporte->resultado('primaria.superficie.aulas')?->mensaje ?? '');
    }

    public function test_sin_superficie_declarada_no_es_evaluable(): void
    {
        $reporte = $this->evaluar(
            [$this->regla('primaria.superficie.aulas', 'ratio_por_alumno', 'aula', 0.90)],
            new DatosCapacidadNivel(matriculaNivel: 100),
        );

        $this->assertSame(EstadoResultado::NoEvaluable, $reporte->resultado('primaria.superficie.aulas')?->estado);
    }

    public function test_preescolar_suma_el_espacio_del_maestro_por_aula_en_un_solo_resultado(): void
    {
        $reglas = [
            $this->regla('preescolar.superficie.aula', 'ratio_por_alumno', 'aula', 1.00),
            $this->regla('preescolar.superficie.espacio_maestro', 'adicional_fijo', 'aula', 2),
        ];

        // 60 educandos × 1 m² + 3 aulas × 2 m² = 66 m²
        $justo = $this->evaluar($reglas, new DatosCapacidadNivel(matriculaNivel: 60, numeroAulas: 3, superficieAulas: 66.0));
        $corto = $this->evaluar($reglas, new DatosCapacidadNivel(matriculaNivel: 60, numeroAulas: 3, superficieAulas: 65.0));

        $this->assertSame(1, $justo->ejecutadas());
        $this->assertSame(EstadoResultado::Cumple, $justo->resultado('preescolar.superficie.aula')?->estado);
        $this->assertSame(EstadoResultado::NoCumple, $corto->resultado('preescolar.superficie.aula')?->estado);
        $this->assertNull($justo->resultado('preescolar.superficie.espacio_maestro'));
    }

    public function test_predio_usa_la_matricula_de_todo_el_plantel(): void
    {
        $regla = $this->regla('primaria.superficie.predio_total', 'ratio_por_alumno', 'predio', 2.50);

        // Nivel 100, plantel (todos los niveles) 160 → 400 m²
        $reporte = $this->evaluar([$regla], new DatosCapacidadNivel(matriculaNivel: 100, matriculaPlantel: 160, superficiePredio: 300.0));

        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('primaria.superficie.predio_total')?->estado);
        $this->assertEqualsWithDelta(400.0, $reporte->resultado('primaria.superficie.predio_total')?->detalles['requerido'], 0.001);
    }

    public function test_area_recreativa_por_ratio_y_minimo_fijo(): void
    {
        $reglas = [
            $this->regla('secundaria.superficie.area_recreacion', 'ratio_por_alumno', 'plantel', 1.25),
            $this->regla('secundaria.superficie.areas_recreativas_minimas', 'minimo_fijo', 'plantel', 200),
        ];

        $reporte = $this->evaluar($reglas, new DatosCapacidadNivel(matriculaNivel: 100, superficieRecreativa: 150.0));

        $this->assertSame(EstadoResultado::Cumple, $reporte->resultado('secundaria.superficie.area_recreacion')?->estado);
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('secundaria.superficie.areas_recreativas_minimas')?->estado);
    }

    public function test_inicial_sala_de_usos_multiples_y_sanitarios(): void
    {
        $reglas = [
            $this->regla('inicial.superficie.sala_usos_multiples', 'ratio_por_alumno', 'plantel', 1.2),
            $this->regla('inicial.superficie.sanitarios', 'ratio_por_alumno', 'plantel', 0.80),
        ];

        $reporte = $this->evaluar($reglas, new DatosCapacidadNivel(matriculaNivel: 20, superficieUsosMultiples: 24.0, superficieSanitariosAlumnos: 15.0));

        $this->assertSame(EstadoResultado::Cumple, $reporte->resultado('inicial.superficie.sala_usos_multiples')?->estado);
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('inicial.superficie.sanitarios')?->estado);
    }

    public function test_acervo_por_grado_y_total(): void
    {
        $primaria = $this->regla('primaria.infraestructura.acervo_bibliografico', 'ratio_por_grado', 'escuela', 50);
        $secundaria = $this->regla('secundaria.infraestructura.acervo_bibliografico', 'minimo_fijo', 'escuela', 300);

        $conSeisGrados = $this->evaluar([$primaria], new DatosCapacidadNivel(gradosOfertados: 6, acervoTitulos: 299));
        $conTresGrados = $this->evaluar([$primaria], new DatosCapacidadNivel(gradosOfertados: 3, acervoTitulos: 150));
        $total = $this->evaluar([$secundaria], new DatosCapacidadNivel(acervoTitulos: 300));

        $this->assertSame(EstadoResultado::NoCumple, $conSeisGrados->resultado('primaria.infraestructura.acervo_bibliografico')?->estado);
        $this->assertSame(EstadoResultado::Cumple, $conTresGrados->resultado('primaria.infraestructura.acervo_bibliografico')?->estado);
        $this->assertSame(EstadoResultado::Cumple, $total->resultado('secundaria.infraestructura.acervo_bibliografico')?->estado);
    }

    public function test_director_obligatorio_cuenta_personal_del_cargo(): void
    {
        $regla = $this->regla('primaria.personal.director_tecnico', 'personal_obligatorio', 'escuela', 1, cargo: self::DIRECTOR);

        $sin = $this->evaluar([$regla], new DatosCapacidadNivel(personalPorCargo: [self::EDUCACION_FISICA => 2]));
        $con = $this->evaluar([$regla], new DatosCapacidadNivel(personalPorCargo: [self::DIRECTOR => 1]));
        $sinPlantilla = $this->evaluar([$regla], new DatosCapacidadNivel);

        $this->assertSame(EstadoResultado::NoCumple, $sin->resultado('primaria.personal.director_tecnico')?->estado);
        $this->assertSame(EstadoResultado::Cumple, $con->resultado('primaria.personal.director_tecnico')?->estado);
        $this->assertSame(EstadoResultado::NoEvaluable, $sinPlantilla->resultado('primaria.personal.director_tecnico')?->estado);
    }

    public function test_educacion_fisica_umbral_de_preescolar(): void
    {
        $regla = $this->regla('preescolar.personal.educacion_fisica', 'personal_umbral', 'escuela', 1, cargo: self::EDUCACION_FISICA, condicionMin: 61);

        $bajoUmbral = $this->evaluar([$regla], new DatosCapacidadNivel(matriculaNivel: 60, personalPorCargo: []));
        $sobreUmbral = $this->evaluar([$regla], new DatosCapacidadNivel(matriculaNivel: 61, personalPorCargo: []));

        $this->assertSame(EstadoResultado::Cumple, $bajoUmbral->resultado('preescolar.personal.educacion_fisica')?->estado);
        $this->assertSame(0, $bajoUmbral->resultado('preescolar.personal.educacion_fisica')?->detalles['requerido']);
        $this->assertSame(EstadoResultado::NoCumple, $sobreUmbral->resultado('preescolar.personal.educacion_fisica')?->estado);
    }

    public function test_educacion_fisica_proporcional_de_primaria(): void
    {
        $regla = $this->regla('primaria.personal.educacion_fisica', 'personal_proporcional', 'escuela', 60, redondeo: 'abajo', cargo: self::EDUCACION_FISICA);

        $reporte = $this->evaluar([$regla], new DatosCapacidadNivel(matriculaNivel: 130, personalPorCargo: [self::EDUCACION_FISICA => 1]));

        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('primaria.personal.educacion_fisica')?->estado);
        $this->assertSame(2, $reporte->resultado('primaria.personal.educacion_fisica')?->detalles['requerido']);
    }

    public function test_educacion_fisica_de_secundaria_cuenta_docentes_de_la_asignatura(): void
    {
        // Seeded without cargo_puesto_id: in Secundaria it is a subject of the "Docente Titular" cargo.
        $regla = $this->regla('secundaria.personal.educacion_fisica', 'personal_umbral', 'escuela', 1, condicionMin: 61);

        $sin = $this->evaluar([$regla], new DatosCapacidadNivel(matriculaNivel: 80, personalPorCargo: [], docentesEducacionFisica: 0));
        $con = $this->evaluar([$regla], new DatosCapacidadNivel(matriculaNivel: 80, personalPorCargo: [], docentesEducacionFisica: 1));

        $this->assertSame(EstadoResultado::NoCumple, $sin->resultado('secundaria.personal.educacion_fisica')?->estado);
        $this->assertSame(EstadoResultado::Cumple, $con->resultado('secundaria.personal.educacion_fisica')?->estado);
    }

    public function test_inicial_un_responsable_por_sala_con_alumnos(): void
    {
        $regla = $this->regla('inicial.personal.responsable_sala', 'personal_por_espacio', 'sala', 1, cargo: self::RESPONSABLE_SALA);
        $salas = ['lactantes_a' => 8, 'lactantes_b' => 0, 'maternal_a' => 12];

        $reporte = $this->evaluar([$regla], new DatosCapacidadNivel(matriculaPorSala: $salas, personalPorCargo: [self::RESPONSABLE_SALA => 1]));

        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('inicial.personal.responsable_sala')?->estado);
        $this->assertSame(2, $reporte->resultado('inicial.personal.responsable_sala')?->detalles['requerido']);
    }

    public function test_inicial_asistentes_se_calculan_por_sala_redondeando_hacia_arriba(): void
    {
        $lactantes = $this->regla('inicial.personal.asistente_lactantes', 'personal_proporcional', 'sala', 5, redondeo: 'arriba', cargo: self::ASISTENTE);
        $maternales = $this->regla('inicial.personal.asistente_maternales', 'personal_proporcional', 'sala', 10, redondeo: 'arriba', cargo: self::ASISTENTE);
        // lactantes_a 6 → 2, lactantes_b 4 → 1 (3 in total, not ⌈10/5⌉ = 2); maternal_a 12 → 2
        $datos = new DatosCapacidadNivel(
            matriculaPorSala: ['lactantes_a' => 6, 'lactantes_b' => 4, 'maternal_a' => 12],
            personalPorCargo: [self::ASISTENTE => 5],
            personalPorCargoYSala: [self::ASISTENTE => ['lactantes_a' => 2, 'lactantes_b' => 1, 'maternal_a' => 1]],
        );

        $reporte = $this->evaluar([$lactantes, $maternales], $datos);

        $this->assertSame(EstadoResultado::Cumple, $reporte->resultado('inicial.personal.asistente_lactantes')?->estado);
        $this->assertSame(3, $reporte->resultado('inicial.personal.asistente_lactantes')?->detalles['requerido']);
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('inicial.personal.asistente_maternales')?->estado);
        $this->assertSame(1, $reporte->resultado('inicial.personal.asistente_maternales')?->detalles['declarado']);
    }

    public function test_reglas_sin_dato_capturable_no_son_evaluables(): void
    {
        $reglas = [
            $this->regla('primaria.infraestructura.altura_aulas', 'minimo_fijo', 'aula', 2.70),
            $this->regla('preescolar.superficie.aula_usos_multiples', 'factor', 'aula', 1.5),
            $this->regla('inicial.superficie.aula_lactantes', 'minimo_fijo', 'sala', 25),
        ];

        $reporte = $this->evaluar($reglas, new DatosCapacidadNivel(matriculaNivel: 50, numeroAulas: 2, superficieAulas: 100.0));

        $this->assertSame(3, $reporte->contar(EstadoResultado::NoEvaluable));
    }
}
