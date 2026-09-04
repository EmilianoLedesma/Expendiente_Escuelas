<?php

namespace Tests\Feature\Database;

use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoMinimoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_niveles_educativos(): void
    {
        (new CatalogoMinimoSeeder())->run();

        $this->assertDatabaseCount('niveles_educativos', 7);
        $this->assertDatabaseHas('niveles_educativos', ['clave' => 'preescolar', 'orden' => 2]);
        $this->assertDatabaseHas('niveles_educativos', ['clave' => 'posgrado', 'orden' => 7]);
    }

    public function test_it_seeds_estados_expediente(): void
    {
        (new CatalogoMinimoSeeder())->run();

        $this->assertDatabaseCount('estados_expediente', 6);
        $this->assertDatabaseHas('estados_expediente', ['clave' => 'preregistro', 'orden' => 1]);
        $this->assertDatabaseHas('estados_expediente', ['clave' => 'rechazado', 'orden' => 6]);
    }

    public function test_it_seeds_salas(): void
    {
        (new CatalogoMinimoSeeder())->run();

        $this->assertDatabaseCount('salas', 5);
        $this->assertDatabaseHas('salas', [
            'clave' => 'lactantes_a',
            'edad_min_meses' => 1,
            'edad_max_meses' => 6,
        ]);
        $this->assertDatabaseHas('salas', [
            'clave' => 'maternal_b',
            'edad_min_meses' => 25,
            'edad_max_meses' => 35,
        ]);
    }

    public function test_it_seeds_tipos_material_biblioteca(): void
    {
        (new CatalogoMinimoSeeder())->run();

        $this->assertDatabaseCount('tipos_material_biblioteca', 9);
        $this->assertDatabaseHas('tipos_material_biblioteca', ['clave' => 'libros']);
        $this->assertDatabaseHas('tipos_material_biblioteca', ['clave' => 'otro']);
    }

    public function test_it_is_idempotent_when_run_twice(): void
    {
        (new CatalogoMinimoSeeder())->run();
        (new CatalogoMinimoSeeder())->run();

        $this->assertDatabaseCount('niveles_educativos', 7);
        $this->assertDatabaseCount('estados_expediente', 6);
        $this->assertDatabaseCount('salas', 5);
        $this->assertDatabaseCount('tipos_material_biblioteca', 9);
    }
}
