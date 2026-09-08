<?php

namespace Tests\Feature\Database;

use Database\Seeders\AsignaturasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsignaturasSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_16_secundaria_asignaturas(): void
    {
        (new AsignaturasSeeder)->run();

        $this->assertDatabaseCount('asignaturas', 16);
        $this->assertDatabaseHas('asignaturas', ['nombre' => 'Biología']);
        $this->assertDatabaseHas('asignaturas', ['nombre' => 'Formación Cívica y Ética']);
        $this->assertDatabaseHas('asignaturas', ['nombre' => 'Asignaturas Extracurriculares']);
    }

    public function test_it_is_idempotent_when_run_twice(): void
    {
        (new AsignaturasSeeder)->run();
        (new AsignaturasSeeder)->run();

        $this->assertDatabaseCount('asignaturas', 16);
    }
}
