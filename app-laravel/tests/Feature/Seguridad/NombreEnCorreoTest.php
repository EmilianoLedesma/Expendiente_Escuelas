<?php

namespace Tests\Feature\Seguridad;

use App\Livewire\Cuenta\Perfil;
use App\Models\User;
use App\Notifications\VerificarCorreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/** Un nombre escrito por el usuario acaba en correos del sistema: no puede traer sintaxis Markdown de enlace. */
class NombreEnCorreoTest extends TestCase
{
    use RefreshDatabase;

    private const NOMBRE_ENLACE = '[Reclama tu premio](https://evil.example)';

    public function test_el_registro_rechaza_un_nombre_con_corchetes_o_parentesis(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', self::NOMBRE_ENLACE)
            ->set('email', 'nuevo@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors('name');

        $this->assertGuest();
    }

    public function test_el_registro_acepta_nombres_con_acentos_y_apostrofos(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', "María-José O'Brien Ñandú")
            ->set('email', 'maria@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasNoErrors();
    }

    public function test_el_perfil_rechaza_un_nombre_con_sintaxis_de_enlace(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Perfil::class)
            ->set('nombre', self::NOMBRE_ENLACE)
            ->call('guardarNombre')
            ->assertHasErrors('nombre');
    }

    public function test_aunque_el_nombre_ya_exista_el_correo_no_lo_convierte_en_enlace(): void
    {
        $usuario = User::factory()->create(['name' => self::NOMBRE_ENLACE]);

        $html = (string) (new VerificarCorreo)->toMail($usuario)->render();

        $this->assertStringNotContainsString('href="https://evil.example"', $html);
    }
}
