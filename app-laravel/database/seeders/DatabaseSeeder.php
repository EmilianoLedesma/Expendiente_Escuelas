<?php

namespace Database\Seeders;

use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Cuenta con contraseña conocida ("password"): solo para desarrollo y pruebas, nunca en producción.
        if (app()->environment(['local', 'testing'])) {
            $testUser = User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

            Solicitante::factory()->create(['user_id' => $testUser->id]);
        }

        $this->call(RolesSeeder::class);
        $this->call(CatalogoMinimoSeeder::class);
        $this->call(GradosSeeder::class);
        $this->call(TiposEspaciosSeeder::class);
        $this->call(MobiliarioConceptosSeeder::class);
        $this->call(TiposDocumentosSeeder::class);
        $this->call(PasosCapturaSeeder::class);
        $this->call(AsignaturasSeeder::class);
        $this->call(CargosPuestosSeeder::class);
        $this->call(PerfilesProfesionalesSeeder::class);
        // ReglasValidacionSeeder enlaza reglas de personal a cargos_puestos por
        // (nivel, nombre) y falla si CargosPuestosSeeder no corrió antes (WS-3.4).
        $this->call(ReglasValidacionSeeder::class);
    }
}
