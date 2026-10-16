<?php

namespace Tests\Feature;

use App\Application\Revision\RutaInicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RutaInicioTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(?string $rol): User
    {
        $u = User::factory()->create();
        if ($rol !== null) {
            $u->assignRole(Role::findOrCreate($rol, 'web'));
        }

        return $u;
    }

    /** @return array<string, array{0: ?string, 1: string}> */
    public static function casos(): array
    {
        return [
            'sedeq' => ['sedeq', '/admin'],
            'supervisor' => ['supervisor', '/sedeq'],
            'revisor' => ['revisor', '/sedeq/cola'],
            'solicitante' => [null, '/tramite'],
        ];
    }

    #[DataProvider('casos')]
    public function test_ruta_para_cada_rol(?string $rol, string $esperada): void
    {
        $this->assertSame($esperada, RutaInicio::para($this->usuario($rol)));
    }

    #[DataProvider('casos')]
    public function test_login_redirige_al_inicio_del_rol(?string $rol, string $esperada): void
    {
        $u = $this->usuario($rol);

        Volt::test('pages.auth.login')
            ->set('form.email', $u->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect($esperada);
    }

    #[DataProvider('casos')]
    public function test_confirm_password_redirige_al_inicio_del_rol(?string $rol, string $esperada): void
    {
        $this->actingAs($this->usuario($rol));

        Volt::test('pages.auth.confirm-password')
            ->set('password', 'password')
            ->call('confirmPassword')
            ->assertRedirect($esperada);
    }

    #[DataProvider('casos')]
    public function test_verify_email_redirige_al_inicio_del_rol(?string $rol, string $esperada): void
    {
        $this->actingAs($this->usuario($rol));

        Volt::test('pages.auth.verify-email')
            ->call('sendVerification')
            ->assertRedirect($esperada);
    }

    public function test_dashboard_redirige_a_supervisor_y_revisor(): void
    {
        $this->actingAs($this->usuario('supervisor'))->get('/dashboard')->assertRedirect('/sedeq');
        $this->actingAs($this->usuario('revisor'))->get('/dashboard')->assertRedirect('/sedeq/cola');
    }

    public function test_tramite_index_redirige_a_supervisor_y_revisor(): void
    {
        $this->actingAs($this->usuario('supervisor'))->get('/tramite')->assertRedirect('/sedeq');
        $this->actingAs($this->usuario('revisor'))->get('/tramite')->assertRedirect('/sedeq/cola');
    }

    public function test_rutas_hud_exigen_el_rol(): void
    {
        $this->actingAs($this->usuario('supervisor'))->get('/sedeq')->assertOk();
        $this->actingAs($this->usuario('supervisor'))->get('/sedeq/cola')->assertForbidden();
        $this->actingAs($this->usuario('revisor'))->get('/sedeq/cola')->assertOk();
        $this->actingAs($this->usuario('revisor'))->get('/sedeq')->assertForbidden();
        $this->actingAs($this->usuario(null))->get('/sedeq')->assertForbidden();
    }
}
