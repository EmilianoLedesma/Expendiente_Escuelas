<?php

namespace Database\Seeders;

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

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(CatalogoMinimoSeeder::class);
        $this->call(PasosCapturaSeeder::class);
        $this->call(AsignaturasSeeder::class);
        $this->call(CargosPuestosSeeder::class);
        $this->call(PerfilesProfesionalesSeeder::class);
        $this->call(ReglasValidacionSeeder::class);
    }
}
