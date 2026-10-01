<?php

namespace Tests\Feature\Validacion;

use App\Livewire\Tramite\Paso1Preregistro;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Los mensajes de validación se muestran junto a cada campo y en el resumen
 * de errores: deben estar en español y nombrar el campo como lo ve el
 * solicitante, no como se llama la propiedad Livewire.
 */
class MensajesEnEspanolTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_idioma_por_defecto_de_la_aplicacion_es_espanol(): void
    {
        $this->assertSame('es', config('app.locale'));
    }

    public function test_correo_invalido_muestra_mensaje_en_espanol_en_el_campo(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user);

        $errores = Livewire::test(Paso1Preregistro::class)
            ->set('correoElectronico', 'no-es-un-correo')
            ->call('guardar')
            ->assertHasErrors('correoElectronico')
            ->errors();

        $this->assertSame('Ingresa un correo electrónico válido, por ejemplo: nombre@dominio.com.', $errores->first('correoElectronico'));
    }

    public function test_campo_obligatorio_usa_el_nombre_visible_del_campo(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user);

        $errores = Livewire::test(Paso1Preregistro::class)
            ->call('guardar')
            ->errors();

        $this->assertSame('El campo calle es obligatorio.', $errores->first('calle'));
        $this->assertSame('El campo código postal es obligatorio.', $errores->first('codigoPostal'));
    }

    public function test_campos_dentro_de_arreglos_y_de_form_objects_sin_nombres_propios_tienen_nombre_visible(): void
    {
        // Un Form object valida con la clave sin prefijo ("curp"); si no
        // declara validationAttributes(), el respaldo del archivo de idioma
        // evita que salga el nombre de la propiedad en crudo.
        $validador = Validator::make(
            ['curp' => '', 'serviciosCercanos' => [['nombre' => '']]],
            ['curp' => 'required', 'serviciosCercanos.*.nombre' => 'required'],
        );

        $this->assertSame('El campo CURP es obligatorio.', $validador->errors()->first('curp'));
        $this->assertSame('El campo nombre del servicio es obligatorio.', $validador->errors()->first('serviciosCercanos.0.nombre'));
    }

    public function test_credenciales_incorrectas_en_login_muestran_mensaje_en_espanol(): void
    {
        $errores = Volt::test('pages.auth.login')
            ->set('form.email', 'nadie@ejemplo.com')
            ->set('form.password', 'incorrecta')
            ->call('login')
            ->assertHasErrors('form.email')
            ->errors();

        $this->assertSame('El correo o la contraseña no son correctos.', $errores->first('form.email'));
    }

    public function test_el_registro_normaliza_el_correo_en_vez_de_rechazar_mayusculas(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', '  Juana   Pérez ')
            ->set('email', ' Juana.Perez@Ejemplo.MX ')
            ->set('password', 'password-segura-123')
            ->set('password_confirmation', 'password-segura-123')
            ->call('register')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Juana Pérez', 'email' => 'juana.perez@ejemplo.mx']);
    }

    public function test_el_login_acepta_el_correo_con_mayusculas_o_espacios(): void
    {
        $usuario = Solicitante::factory()->create()->user;

        Volt::test('pages.auth.login')
            ->set('form.email', '  '.strtoupper($usuario->email).' ')
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($usuario);
    }
}
