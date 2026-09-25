<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthVistasTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_usa_campos_etiquetados_del_sistema(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('for="form.email"', false)
            ->assertSee('for="form.password"', false)
            ->assertSee('for="form.remember"', false)
            ->assertSee('Iniciar sesión')
            ->assertSee('font-display', false);
    }

    public function test_login_fallido_muestra_el_resumen_de_errores(): void
    {
        Volt::test('pages.auth.login')
            ->set('form.email', 'nadie@example.com')
            ->set('form.password', 'incorrecta')
            ->call('login')
            ->assertSee('Revisa los siguientes datos')
            ->assertSeeHtml('href="#form.email"');
    }

    public function test_registro_etiqueta_cada_campo(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();

        foreach (['name', 'email', 'password', 'password_confirmation'] as $id) {
            $this->assertStringContainsString('for="'.$id.'"', $html, $id);
        }
    }

    public function test_recuperar_contrasena_etiqueta_el_correo(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('for="email"', false);
    }

    public function test_restablecer_contrasena_esta_en_espanol_y_sin_clases_de_breeze(): void
    {
        $this->get('/reset-password/token-de-prueba?email=a@example.com')
            ->assertOk()
            ->assertSee('Restablecer contraseña')
            ->assertSee('for="password_confirmation"', false)
            ->assertDontSee('text-gray-600', false);
    }

    public function test_verificar_correo_esta_en_espanol(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/verify-email')
            ->assertOk()
            ->assertSee('Reenviar correo de verificación')
            ->assertSee('Cerrar sesión')
            ->assertDontSee('Thanks for signing up');
    }

    public function test_confirmar_contrasena_esta_en_espanol(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/confirm-password')
            ->assertOk()
            ->assertSee('for="password"', false)
            ->assertSee('Confirmar')
            ->assertDontSee('This is a secure area');
    }
}
