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

    public function test_it_seeds_28_reglas_across_4_niveles(): void
    {
        (new CatalogoMinimoSeeder())->run();
        (new ReglasValidacionSeeder())->run();

        $this->assertDatabaseCount('reglas_validacion', 28);
    }

    public function test_it_seeds_inicial_superficie_rules(): void
    {
        (new CatalogoMinimoSeeder())->run();
        (new ReglasValidacionSeeder())->run();

        $inicialId = DB::table('niveles_educativos')->where('clave', 'inicial')->value('id');

        $this->assertDatabaseHas('reglas_validacion', [
            'nivel_educativo_id' => $inicialId,
            'tipo_regla' => 'superficie',
            'concepto' => 'Superficie mínima de aula (Lactantes)',
            'condicion_max' => 10,
            'valor_numerico' => 25.00,
            'unidad' => 'm²/aula',
        ]);
    }

    public function test_it_seeds_preescolar_personal_rule_with_condicion_min(): void
    {
        (new CatalogoMinimoSeeder())->run();
        (new ReglasValidacionSeeder())->run();

        $preescolarId = DB::table('niveles_educativos')->where('clave', 'preescolar')->value('id');

        $this->assertDatabaseHas('reglas_validacion', [
            'nivel_educativo_id' => $preescolarId,
            'tipo_regla' => 'personal',
            'concepto' => 'Docente de Educación Física obligatorio',
            'condicion_min' => 61,
        ]);
    }

    public function test_it_seeds_secundaria_trabajador_social_y_prefecto(): void
    {
        (new CatalogoMinimoSeeder())->run();
        (new ReglasValidacionSeeder())->run();

        $secundariaId = DB::table('niveles_educativos')->where('clave', 'secundaria')->value('id');

        $this->assertDatabaseHas('reglas_validacion', [
            'nivel_educativo_id' => $secundariaId,
            'tipo_regla' => 'personal',
            'concepto' => 'Trabajador social obligatorio',
        ]);
        $this->assertDatabaseHas('reglas_validacion', [
            'nivel_educativo_id' => $secundariaId,
            'tipo_regla' => 'personal',
            'concepto' => 'Prefecto obligatorio',
        ]);
    }

    public function test_it_is_idempotent_when_run_twice(): void
    {
        (new CatalogoMinimoSeeder())->run();
        (new ReglasValidacionSeeder())->run();
        (new ReglasValidacionSeeder())->run();

        $this->assertDatabaseCount('reglas_validacion', 28);
    }
}
