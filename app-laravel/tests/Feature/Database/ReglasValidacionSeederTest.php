<?php

namespace Tests\Feature\Database;

use App\Domain\Validaciones\Regla\CalculadoraRequerimiento;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\ReglasValidacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReglasValidacionSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedReglas(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new ReglasValidacionSeeder)->run();
    }

    public function test_it_seeds_28_reglas_across_4_niveles(): void
    {
        $this->seedReglas();

        $this->assertDatabaseCount('reglas_validacion', 28);
    }

    public function test_clave_is_unique_and_never_null(): void
    {
        $this->seedReglas();

        $claves = DB::table('reglas_validacion')->pluck('clave');

        $this->assertCount(28, $claves);
        $this->assertCount(28, $claves->unique());
        $this->assertFalse($claves->contains(null));
    }

    public function test_tipo_calculo_ambito_and_redondeo_are_never_null(): void
    {
        $this->seedReglas();

        $this->assertSame(
            0,
            DB::table('reglas_validacion')
                ->whereNull('tipo_calculo')
                ->orWhereNull('ambito')
                ->orWhereNull('redondeo')
                ->count()
        );
    }

    public function test_every_tipo_calculo_value_is_one_of_the_nine_allowed(): void
    {
        $this->seedReglas();

        $allowed = [
            'ratio_por_alumno', 'ratio_por_grado', 'minimo_fijo',
            'adicional_fijo', 'factor', 'personal_obligatorio',
            'personal_umbral', 'personal_proporcional', 'personal_por_espacio',
        ];

        $used = DB::table('reglas_validacion')->distinct()->pluck('tipo_calculo');

        foreach ($used as $value) {
            $this->assertContains($value, $allowed);
        }
    }

    public function test_every_redondeo_value_is_arriba_abajo_or_na(): void
    {
        $this->seedReglas();

        $used = DB::table('reglas_validacion')->distinct()->pluck('redondeo');

        foreach ($used as $value) {
            $this->assertContains($value, ['arriba', 'abajo', 'na']);
        }
    }

    /**
     * Only personal_proporcional rows carry a real division/rounding
     * decision. Every other tipo_calculo is either a constant, a flat gate,
     * or a continuous multiplication with no discrete-unit rounding
     * question — see the report's rounding audit table. This test locks
     * that boundary in: any row that is NOT personal_proporcional must be
     * 'na'.
     */
    public function test_only_personal_proporcional_rows_use_arriba_or_abajo(): void
    {
        $this->seedReglas();

        $nonNa = DB::table('reglas_validacion')->where('redondeo', '!=', 'na')->get();

        $this->assertCount(3, $nonNa);
        foreach ($nonNa as $row) {
            $this->assertSame('personal_proporcional', $row->tipo_calculo, "clave={$row->clave}");
        }
    }

    public function test_tipo_regla_distribution_after_infraestructura_reclassification(): void
    {
        $this->seedReglas();

        $counts = DB::table('reglas_validacion')
            ->selectRaw('tipo_regla, count(*) as total')
            ->groupBy('tipo_regla')
            ->pluck('total', 'tipo_regla');

        $this->assertSame(16, $counts['superficie']);
        $this->assertSame(9, $counts['personal']);
        $this->assertSame(3, $counts['infraestructura']);
    }

    /**
     * Taxonomy-leak fix: these three rows measure linear metres or títulos
     * (book counts), not area — they do not belong under tipo_regla =
     * superficie. See report §"Auditoría de tipo_regla".
     */
    public function test_infraestructura_rows_are_the_three_non_area_ones(): void
    {
        $this->seedReglas();

        $claves = DB::table('reglas_validacion')->where('tipo_regla', 'infraestructura')->pluck('clave')->sort()->values();

        $this->assertSame([
            'primaria.infraestructura.acervo_bibliografico',
            'primaria.infraestructura.altura_aulas',
            'secundaria.infraestructura.acervo_bibliografico',
        ], $claves->all());
    }

    public function test_it_is_idempotent_when_run_twice(): void
    {
        $this->seedReglas();
        (new ReglasValidacionSeeder)->run();

        $this->assertDatabaseCount('reglas_validacion', 28);
    }

    public function test_ratio_por_alumno_rule_inicial_area_recreativa(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'inicial.superficie.area_recreativa',
            'tipo_calculo' => 'ratio_por_alumno',
            'ambito' => 'plantel',
            'redondeo' => 'na',
            'valor_numerico' => 1.00,
        ]);
    }

    public function test_ratio_por_grado_rule_primaria_acervo_bibliografico(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'primaria.infraestructura.acervo_bibliografico',
            'tipo_regla' => 'infraestructura',
            'tipo_calculo' => 'ratio_por_grado',
            'ambito' => 'escuela',
            'redondeo' => 'na',
            'valor_numerico' => 50.00,
        ]);
    }

    public function test_minimo_fijo_rule_inicial_aula_lactantes(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'inicial.superficie.aula_lactantes',
            'tipo_calculo' => 'minimo_fijo',
            'ambito' => 'sala',
            'redondeo' => 'na',
            'condicion_max' => 10.00,
            'valor_numerico' => 25.00,
        ]);
    }

    public function test_minimo_fijo_rule_primaria_altura_aulas_is_infraestructura(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'primaria.infraestructura.altura_aulas',
            'tipo_regla' => 'infraestructura',
            'tipo_calculo' => 'minimo_fijo',
            'ambito' => 'aula',
            'redondeo' => 'na',
            'valor_numerico' => 2.70,
            'unidad' => 'm fijo',
        ]);
    }

    public function test_adicional_fijo_rule_preescolar_espacio_maestro(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'preescolar.superficie.espacio_maestro',
            'tipo_calculo' => 'adicional_fijo',
            'ambito' => 'aula',
            'redondeo' => 'na',
            'valor_numerico' => 2.00,
        ]);
    }

    public function test_factor_rule_preescolar_aula_usos_multiples(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'preescolar.superficie.aula_usos_multiples',
            'tipo_calculo' => 'factor',
            'ambito' => 'aula',
            'redondeo' => 'na',
            'valor_numerico' => 1.50,
        ]);
    }

    public function test_personal_obligatorio_rule_inicial_director_tecnico(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'inicial.personal.director_tecnico',
            'tipo_calculo' => 'personal_obligatorio',
            'ambito' => 'plantel',
            'redondeo' => 'na',
            'valor_numerico' => 1.00,
        ]);
    }

    public function test_personal_por_espacio_rule_inicial_responsable_sala(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'inicial.personal.responsable_sala',
            'tipo_calculo' => 'personal_por_espacio',
            'ambito' => 'sala',
            'redondeo' => 'na',
            'valor_numerico' => 1.00,
        ]);
    }

    /**
     * Rounding audit finding: COMPENDIO línea 422 states explicitly
     * "(redondeo hacia arriba)" for both Inicial asistente ratios. This is
     * the OPPOSITE rounding direction from Primaria's PE rule, despite both
     * being tipo_calculo = personal_proporcional — the exact ambiguity the
     * `redondeo` column exists to resolve.
     */
    public function test_personal_proporcional_rule_inicial_asistente_lactantes_rounds_up(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'inicial.personal.asistente_lactantes',
            'tipo_calculo' => 'personal_proporcional',
            'ambito' => 'sala',
            'redondeo' => 'arriba',
            'valor_numerico' => 5.00,
        ]);
    }

    public function test_personal_proporcional_rule_inicial_asistente_maternales_rounds_up(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'inicial.personal.asistente_maternales',
            'tipo_calculo' => 'personal_proporcional',
            'ambito' => 'sala',
            'redondeo' => 'arriba',
            'valor_numerico' => 10.00,
        ]);
    }

    public function test_personal_umbral_rule_secundaria_prefecto(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'secundaria.personal.prefecto',
            'tipo_calculo' => 'personal_umbral',
            'ambito' => 'escuela',
            'redondeo' => 'na',
            'condicion_min' => 61.00,
            'valor_numerico' => 1.00,
        ]);
    }

    /**
     * Normative fix 1 (STILL PROVISIONAL — see
     * docs/decisions/PENDIENTE-umbral-educacion-fisica.md, which is the
     * authority on this question, not this comment): condicion_min = 61 is
     * the value currently seeded for the *_umbral rows. It is not settled.
     */
    public function test_normative_fix_educacion_fisica_threshold_is_61_not_60(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'preescolar.personal.educacion_fisica',
            'condicion_min' => 61.00,
        ]);
    }

    /**
     * Normative fix 2: COMPENDIO §5.1 distinguishes Preescolar ("solo si la
     * instalación tiene capacidad de 60 alumnos o más" — a threshold, one
     * teacher) from Primaria ("por cada 60 alumnos o más en la escuela...
     * se puede requerir más de uno" — proportional). Preescolar must use
     * personal_umbral; Primaria must use personal_proporcional.
     */
    public function test_normative_fix_preescolar_is_umbral_primaria_is_proporcional(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'preescolar.personal.educacion_fisica',
            'tipo_calculo' => 'personal_umbral',
            'valor_numerico' => 1.00,
        ]);

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'primaria.personal.educacion_fisica',
            'tipo_calculo' => 'personal_proporcional',
            'redondeo' => 'abajo',
            'condicion_min' => null,
            'valor_numerico' => 60.00,
        ]);
    }

    /**
     * Regression + real formula: previously this test computed the
     * expected headcount inline with intdiv(), which only proves PHP's
     * integer division works — not that this system's rule for Primaria's
     * PE requirement is right. It now loads the actual row from the DB and
     * runs it through CalculadoraRequerimiento, the same class the engine
     * will use. If the engine's rounding rule changes, this test breaks
     * for the right reason instead of staying green.
     */
    #[DataProvider('primariaEducacionFisicaBoundaryProvider')]
    public function test_primaria_educacion_fisica_boundary(int $enrollment, int $expectedDocentes): void
    {
        $this->seedReglas();

        $rule = DB::table('reglas_validacion')->where('clave', 'primaria.personal.educacion_fisica')->first();

        $required = (new CalculadoraRequerimiento)->calcular(
            tipoCalculo: $rule->tipo_calculo,
            valorNumerico: (float) $rule->valor_numerico,
            condicionMin: $rule->condicion_min !== null ? (float) $rule->condicion_min : null,
            redondeo: $rule->redondeo,
            magnitud: (float) $enrollment,
        );

        $this->assertSame($expectedDocentes, $required, "enrollment={$enrollment}");
    }

    public static function primariaEducacionFisicaBoundaryProvider(): array
    {
        return [
            '59 alumnos -> 0 docentes' => [59, 0],
            '60 alumnos -> 1 docente' => [60, 1],
            '61 alumnos -> 1 docente' => [61, 1],
            '119 alumnos -> 1 docente' => [119, 1],
            '120 alumnos -> 2 docentes' => [120, 2],
            '121 alumnos -> 2 docentes' => [121, 2],
        ];
    }

    #[DataProvider('preescolarEducacionFisicaBoundaryProvider')]
    public function test_preescolar_educacion_fisica_boundary(int $enrollment, int $expectedDocentes): void
    {
        $this->seedReglas();

        $rule = DB::table('reglas_validacion')->where('clave', 'preescolar.personal.educacion_fisica')->first();

        $required = (new CalculadoraRequerimiento)->calcular(
            tipoCalculo: $rule->tipo_calculo,
            valorNumerico: (float) $rule->valor_numerico,
            condicionMin: (float) $rule->condicion_min,
            redondeo: $rule->redondeo,
            magnitud: (float) $enrollment,
        );

        $this->assertSame($expectedDocentes, $required, "enrollment={$enrollment}");
    }

    public static function preescolarEducacionFisicaBoundaryProvider(): array
    {
        return [
            '59 alumnos -> 0 docentes' => [59, 0],
            '60 alumnos -> 0 docentes (umbral provisional = 61, ver decision memo)' => [60, 0],
            '61 alumnos -> 1 docente' => [61, 1],
        ];
    }

    /**
     * New boundary coverage per COMPENDIO línea 422 ("redondeo hacia
     * arriba"): a Lactantes room with 6 infants needs 2 asistentes, not 1 —
     * the concrete safety-relevant case named in the follow-up request.
     */
    #[DataProvider('inicialAsistenteLactantesBoundaryProvider')]
    public function test_inicial_asistente_lactantes_boundary(int $infantes, int $expectedAsistentes): void
    {
        $this->seedReglas();

        $rule = DB::table('reglas_validacion')->where('clave', 'inicial.personal.asistente_lactantes')->first();

        $required = (new CalculadoraRequerimiento)->calcular(
            tipoCalculo: $rule->tipo_calculo,
            valorNumerico: (float) $rule->valor_numerico,
            condicionMin: null,
            redondeo: $rule->redondeo,
            magnitud: (float) $infantes,
        );

        $this->assertSame($expectedAsistentes, $required, "infantes={$infantes}");
    }

    public static function inicialAsistenteLactantesBoundaryProvider(): array
    {
        return [
            '4 infantes -> 1 asistente' => [4, 1],
            '5 infantes -> 1 asistente' => [5, 1],
            '6 infantes -> 2 asistentes' => [6, 2],
            '10 infantes -> 2 asistentes' => [10, 2],
            '11 infantes -> 3 asistentes' => [11, 3],
        ];
    }

    #[DataProvider('inicialAsistenteMaternalesBoundaryProvider')]
    public function test_inicial_asistente_maternales_boundary(int $ninos, int $expectedAsistentes): void
    {
        $this->seedReglas();

        $rule = DB::table('reglas_validacion')->where('clave', 'inicial.personal.asistente_maternales')->first();

        $required = (new CalculadoraRequerimiento)->calcular(
            tipoCalculo: $rule->tipo_calculo,
            valorNumerico: (float) $rule->valor_numerico,
            condicionMin: null,
            redondeo: $rule->redondeo,
            magnitud: (float) $ninos,
        );

        $this->assertSame($expectedAsistentes, $required, "ninos={$ninos}");
    }

    public static function inicialAsistenteMaternalesBoundaryProvider(): array
    {
        return [
            '9 niños -> 1 asistente' => [9, 1],
            '10 niños -> 1 asistente' => [10, 1],
            '11 niños -> 2 asistentes' => [11, 2],
        ];
    }
}
