<?php

namespace Tests\Feature\Auth;

use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrearSolicitanteAlRegistrarUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_solicitante_cuando_se_dispara_registered(): void
    {
        $user = User::factory()->create();

        event(new Registered($user));

        $this->assertDatabaseHas('solicitantes', ['user_id' => $user->id]);
    }

    public function test_el_flujo_real_de_registro_crea_el_solicitante(): void
    {
        $component = \Livewire\Volt\Volt::test('pages.auth.register')
            ->set('name', 'Ana Solicitante')
            ->set('email', 'ana@example.com')
            ->set('password', 'password-valido')
            ->set('password_confirmation', 'password-valido');

        $component->call('register');

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertDatabaseHas('solicitantes', ['user_id' => $user->id]);
    }
}
