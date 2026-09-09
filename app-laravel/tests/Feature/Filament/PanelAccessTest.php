<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_sin_rol_sedeq_no_puede_entrar_al_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_un_usuario_con_rol_sedeq_puede_entrar_al_panel(): void
    {
        Role::create(['name' => 'sedeq', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('sedeq');

        $this->actingAs($user)->get('/admin')->assertOk();
    }
}
