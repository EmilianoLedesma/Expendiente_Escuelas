<?php

namespace Tests\Feature\Database;

use Database\Seeders\CargosPuestosSeeder;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PerfilesProfesionalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerfilesProfesionalesSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedDependencies(): void
    {
        (new CatalogoMinimoSeeder())->run();
        (new CargosPuestosSeeder())->run();
    }

    public function test_it_seeds_87_perfiles_across_inicial_preescolar_primaria_secundaria(): void
    {
        $this->seedDependencies();
        (new PerfilesProfesionalesSeeder())->run();

        $this->assertDatabaseCount('perfiles_profesionales', 87);
    }

    public function test_it_seeds_inicial_director_tecnico_perfiles(): void
    {
        $this->seedDependencies();
        (new PerfilesProfesionalesSeeder())->run();

        $inicialId = DB::table('niveles_educativos')->where('clave', 'inicial')->value('id');
        $cargoId = DB::table('cargos_puestos')
            ->where('nivel_educativo_id', $inicialId)
            ->where('nombre', 'Director Técnico')
            ->value('id');

        $this->assertDatabaseHas('perfiles_profesionales', [
            'cargo_puesto_id' => $cargoId,
            'carrera_aceptada' => 'Psicología',
            'documento_acreditacion' => 'titulo_cedula',
        ]);
    }

    public function test_it_seeds_preescolar_docente_ingles_certifications(): void
    {
        $this->seedDependencies();
        (new PerfilesProfesionalesSeeder())->run();

        $preescolarId = DB::table('niveles_educativos')->where('clave', 'preescolar')->value('id');
        $cargoId = DB::table('cargos_puestos')
            ->where('nivel_educativo_id', $preescolarId)
            ->where('nombre', 'Docente de Inglés')
            ->value('id');

        $this->assertDatabaseHas('perfiles_profesionales', [
            'cargo_puesto_id' => $cargoId,
            'carrera_aceptada' => 'Certificación MCER',
            'documento_acreditacion' => 'certificado',
        ]);
        $this->assertEquals(9, DB::table('perfiles_profesionales')->where('cargo_puesto_id', $cargoId)->count());
    }

    public function test_it_seeds_primaria_docente_computacion_perfiles(): void
    {
        $this->seedDependencies();
        (new PerfilesProfesionalesSeeder())->run();

        $primariaId = DB::table('niveles_educativos')->where('clave', 'primaria')->value('id');
        $cargoId = DB::table('cargos_puestos')
            ->where('nivel_educativo_id', $primariaId)
            ->where('nombre', 'Docente de Computación')
            ->value('id');

        $this->assertEquals(4, DB::table('perfiles_profesionales')->where('cargo_puesto_id', $cargoId)->count());
        $this->assertDatabaseHas('perfiles_profesionales', [
            'cargo_puesto_id' => $cargoId,
            'carrera_aceptada' => 'Tecnologías de la Información',
        ]);
    }

    public function test_it_seeds_secundaria_director_tecnico_perfiles(): void
    {
        $this->seedDependencies();
        (new PerfilesProfesionalesSeeder())->run();

        $secundariaId = DB::table('niveles_educativos')->where('clave', 'secundaria')->value('id');
        $cargoId = DB::table('cargos_puestos')
            ->where('nivel_educativo_id', $secundariaId)
            ->where('nombre', 'Director Técnico')
            ->value('id');

        $this->assertEquals(7, DB::table('perfiles_profesionales')->where('cargo_puesto_id', $cargoId)->count());
        $this->assertDatabaseHas('perfiles_profesionales', [
            'cargo_puesto_id' => $cargoId,
            'carrera_aceptada' => 'Pedagogía',
            'documento_acreditacion' => 'titulo_cedula',
        ]);
    }

    public function test_it_is_idempotent_when_run_twice(): void
    {
        $this->seedDependencies();
        (new PerfilesProfesionalesSeeder())->run();
        (new PerfilesProfesionalesSeeder())->run();

        $this->assertDatabaseCount('perfiles_profesionales', 87);
    }

    public function test_seeder_does_not_delete_secundaria_perfiles_outside_its_scope(): void
    {
        $this->seedDependencies();

        // Insert a dummy perfil for Secundaria Docente Titular
        $secundariaId = DB::table('niveles_educativos')->where('clave', 'secundaria')->value('id');
        $secundariaDocenteTitularId = DB::table('cargos_puestos')
            ->where('nivel_educativo_id', $secundariaId)
            ->where('nombre', 'Docente Titular')
            ->value('id');

        DB::table('perfiles_profesionales')->insert([
            'cargo_puesto_id' => $secundariaDocenteTitularId,
            'carrera_aceptada' => 'Licenciatura en Química',
            'documento_acreditacion' => 'titulo_cedula',
        ]);

        // Run the seeder (covers only Inicial/Preescolar/Primaria)
        (new PerfilesProfesionalesSeeder())->run();

        // Assert the Secundaria row still exists — seeder's delete should not touch it
        $this->assertDatabaseHas('perfiles_profesionales', [
            'cargo_puesto_id' => $secundariaDocenteTitularId,
            'carrera_aceptada' => 'Licenciatura en Química',
        ]);
    }
}
