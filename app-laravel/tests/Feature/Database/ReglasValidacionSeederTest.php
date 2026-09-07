<?php

namespace Tests\Feature\Database;

use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\ReglasValidacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReglasValidacionSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedReglas(): void
    {
        (new CatalogoMinimoSeeder())->run();
        (new ReglasValidacionSeeder())->run();
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

    public function test_tipo_calculo_and_ambito_are_never_null(): void
    {
        $this->seedReglas();

        $this->assertSame(
            0,
            DB::table('reglas_validacion')->whereNull('tipo_calculo')->orWhereNull('ambito')->count()
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

    public function test_it_is_idempotent_when_run_twice(): void
    {
        $this->seedReglas();
        (new ReglasValidacionSeeder())->run();

        $this->assertDatabaseCount('reglas_validacion', 28);
    }

    public function test_ratio_por_alumno_rule_inicial_area_recreativa(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'inicial.superficie.area_recreativa',
            'tipo_calculo' => 'ratio_por_alumno',
            'ambito' => 'plantel',
            'valor_numerico' => 1.00,
        ]);
    }

    public function test_ratio_por_grado_rule_primaria_acervo_bibliografico(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'primaria.superficie.acervo_bibliografico',
            'tipo_calculo' => 'ratio_por_grado',
            'ambito' => 'escuela',
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
            'condicion_max' => 10.00,
            'valor_numerico' => 25.00,
        ]);
    }

    public function test_adicional_fijo_rule_preescolar_espacio_maestro(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'preescolar.superficie.espacio_maestro',
            'tipo_calculo' => 'adicional_fijo',
            'ambito' => 'aula',
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
            'valor_numerico' => 1.00,
        ]);
    }

    public function test_personal_proporcional_rule_inicial_asistente_lactantes(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'inicial.personal.asistente_lactantes',
            'tipo_calculo' => 'personal_proporcional',
            'ambito' => 'sala',
            'valor_numerico' => 5.00,
        ]);
    }

    public function test_personal_umbral_rule_secundaria_prefecto(): void
    {
        $this->seedReglas();

        $this->assertDatabaseHas('reglas_validacion', [
            'clave' => 'secundaria.personal.prefecto',
            'tipo_calculo' => 'personal_umbral',
            'ambito' => 'escuela',
            'condicion_min' => 61.00,
            'valor_numerico' => 1.00,
        ]);
    }

    /**
     * Normative fix 1: COMPENDIO §5.2 (Acuerdos Secretariales 357/254/255) and
     * the summary table (line ~640) consistently say "más de 60 alumnos" for
     * all three niveles — a strict >60 threshold, i.e. condicion_min = 61.
     * §5.1's earlier prose ("60 alumnos o más") is the outlier and is
     * superseded per the 2026-09-04 decision (docs/progress.md Decisions Log).
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
            'condicion_min' => 61.00,
            'valor_numerico' => 60.00,
        ]);
    }
}
