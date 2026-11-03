<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CuentaArchivadaTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE = 'Cuenta archivada, contacta a SEDEQ.';

    private function archivado(): User
    {
        return User::factory()->create(['archivado_at' => now()]);
    }

    public function test_archivado_con_password_correcta_ve_mensaje_y_no_entra(): void
    {
        $user = $this->archivado();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors(['form.email'])
            ->assertSee(self::MENSAJE);

        $this->assertGuest();
    }

    public function test_archivado_con_password_incorrecta_no_filtra_el_estado(): void
    {
        $user = $this->archivado();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'incorrecta')
            ->call('login')
            ->assertHasErrors(['form.email'])
            ->assertDontSee(self::MENSAJE)
            ->assertSee(trans('auth.failed'));

        $this->assertGuest();
    }

    public function test_sesion_abierta_se_cierra_en_la_siguiente_peticion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['archivado_at' => now()])->save();

        $this->get(route('tramite.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', self::MENSAJE);

        $this->assertGuest();
    }

    public function test_sesion_abierta_del_panel_admin_se_cierra(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
        $user->forceFill(['archivado_at' => now()])->save();

        $this->get('/admin')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_el_correo_de_un_archivado_sigue_reservado_al_registrar(): void
    {
        $user = $this->archivado();

        Volt::test('pages.auth.register')
            ->set('name', 'Otro')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors(['email' => 'unique']);

        $this->assertGuest();
    }
}
