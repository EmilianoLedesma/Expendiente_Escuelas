<?php

namespace Tests\Feature\Database;

use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasosCapturaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_six_paso3_substeps_in_order(): void
    {
        (new PasosCapturaSeeder)->run();

        $this->assertDatabaseCount('pasos_captura', 6);

        $this->assertDatabaseHas('pasos_captura', ['clave' => 'inmueble', 'orden' => 1]);
        $this->assertDatabaseHas('pasos_captura', ['clave' => 'infraestructura', 'orden' => 2]);
        $this->assertDatabaseHas('pasos_captura', ['clave' => 'mobiliario', 'orden' => 3]);
        $this->assertDatabaseHas('pasos_captura', ['clave' => 'plan_estudios', 'orden' => 4]);
        $this->assertDatabaseHas('pasos_captura', ['clave' => 'plantilla_docente', 'orden' => 5]);
        $this->assertDatabaseHas('pasos_captura', ['clave' => 'matricula', 'orden' => 6]);
    }

    public function test_it_is_idempotent_when_run_twice(): void
    {
        (new PasosCapturaSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $this->assertDatabaseCount('pasos_captura', 6);
    }
}
