<?php

namespace Tests\Feature\Database;

use Database\Seeders\CargosPuestosSeeder;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CargosPuestosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_16_cargos_across_4_niveles(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new CargosPuestosSeeder)->run();

        $this->assertDatabaseCount('cargos_puestos', 16);
    }

    public function test_it_seeds_inicial_cargos_with_correct_flags(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new CargosPuestosSeeder)->run();

        $inicialId = DB::table('niveles_educativos')->where('clave', 'inicial')->value('id');

        $this->assertDatabaseHas('cargos_puestos', [
            'nivel_educativo_id' => $inicialId,
            'nombre' => 'Responsable de Sala',
            'requiere_sala' => true,
            'requiere_asignatura' => false,
        ]);
        $this->assertDatabaseHas('cargos_puestos', [
            'nivel_educativo_id' => $inicialId,
            'nombre' => 'Director Técnico',
            'requiere_sala' => false,
        ]);
    }

    public function test_it_seeds_secundaria_docente_titular_requiring_asignatura(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new CargosPuestosSeeder)->run();

        $secundariaId = DB::table('niveles_educativos')->where('clave', 'secundaria')->value('id');

        $this->assertDatabaseHas('cargos_puestos', [
            'nivel_educativo_id' => $secundariaId,
            'nombre' => 'Docente Titular',
            'requiere_asignatura' => true,
        ]);
    }

    public function test_it_is_idempotent_when_run_twice(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new CargosPuestosSeeder)->run();
        (new CargosPuestosSeeder)->run();

        $this->assertDatabaseCount('cargos_puestos', 16);
    }
}
