<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RenombrarRolSedeqTest extends TestCase
{
    use RefreshDatabase;

    private function migrar(): void
    {
        (require database_path('migrations/2026_11_03_000000_renombrar_rol_sedeq_a_admin.php'))->up();
    }

    public function test_renombra_sedeq_a_admin_conservando_sus_titulares(): void
    {
        Role::create(['name' => 'sedeq', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('sedeq');

        $this->migrar();

        $this->assertDatabaseMissing('roles', ['name' => 'sedeq']);
        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_con_ambos_roles_mueve_titulares_y_borra_sedeq(): void
    {
        Role::create(['name' => 'sedeq', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $solo = User::factory()->create()->assignRole('sedeq');
        $ambos = User::factory()->create()->assignRole('sedeq', 'admin');

        $this->migrar();

        $this->assertDatabaseMissing('roles', ['name' => 'sedeq']);
        $this->assertTrue($solo->fresh()->hasRole('admin'));
        $this->assertTrue($ambos->fresh()->hasRole('admin'));
        $this->assertSame(1, $ambos->fresh()->roles()->count());
    }

    public function test_es_idempotente(): void
    {
        $this->migrar();
        $this->migrar();
        $this->assertDatabaseMissing('roles', ['name' => 'sedeq']);
    }
}
