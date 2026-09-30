<?php

namespace Tests\Feature\Validacion;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Livewire\Component;
use Livewire\Form;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Livewire no pasa sus peticiones por TrimStrings, así que la limpieza de
 * lo capturado y la validación al salir de cada campo las hace un hook
 * global (App\Livewire\Hooks\LimpiarYValidarAlCapturar) para todo
 * componente, incluidos los Form objects y las páginas Volt de auth.
 */
class LimpiezaYValidacionEnVivoTest extends TestCase
{
    public function test_recorta_y_colapsa_espacios_de_cualquier_texto(): void
    {
        Livewire::test(ComponentePrueba::class)
            ->set('calle', "  Av.   Reforma \t 100  ")
            ->assertSet('calle', 'Av. Reforma 100');
    }

    public function test_aplica_la_normalizacion_declarada_en_un_form_object(): void
    {
        Livewire::test(ComponentePrueba::class)
            ->set('formulario.curp', ' goma-800101 hqtrrl09 ')
            ->assertSet('formulario.curp', 'GOMA800101HQTRRL09')
            ->assertHasNoErrors('formulario.curp');
    }

    public function test_limpia_texto_dentro_de_arreglos(): void
    {
        Livewire::test(ComponentePrueba::class)
            ->set('servicios.0.nombre', '  Cruz   Roja ')
            ->assertSet('servicios.0.nombre', 'Cruz Roja');
    }

    public function test_no_toca_contrasenas(): void
    {
        Livewire::test(ComponentePrueba::class)
            ->set('password', '  con espacios  ')
            ->assertSet('password', '  con espacios  ');
    }

    public function test_valida_el_campo_en_cuanto_se_captura_y_limpia_el_error_al_corregirlo(): void
    {
        $prueba = Livewire::test(ComponentePrueba::class)
            ->set('correo', 'no-es-correo')
            ->assertHasErrors(['correo']);

        $this->assertSame('Ingresa un correo electrónico válido, por ejemplo: nombre@dominio.com.', $prueba->errors()->first('correo'));

        $prueba->set('correo', 'direccion@escuela.mx')->assertHasNoErrors('correo');
    }

    public function test_valida_en_vivo_los_campos_de_un_form_object_con_su_nombre_visible(): void
    {
        $prueba = Livewire::test(ComponentePrueba::class)
            ->set('formulario.curp', 'ABC')
            ->assertHasErrors(['formulario.curp']);

        $this->assertSame('La CURP del titular no tiene un formato válido: son 18 caracteres, por ejemplo GOMA800101HQTRRL09.', $prueba->errors()->first('formulario.curp'));
    }

    public function test_validar_un_campo_no_borra_los_errores_de_otros(): void
    {
        Livewire::test(ComponentePrueba::class)
            ->set('correo', 'mal')
            ->set('calle', 'Centro')
            ->assertHasErrors(['correo']);
    }

    public function test_un_campo_sin_regla_no_falla(): void
    {
        Livewire::test(ComponentePrueba::class)
            ->set('sinRegla', '  algo ')
            ->assertSet('sinRegla', 'algo')
            ->assertHasNoErrors();
    }

    public function test_no_convierte_valores_que_no_son_texto(): void
    {
        Livewire::test(ComponentePrueba::class)
            ->set('numero', 5)
            ->assertSet('numero', 5);
    }
}

class FormularioPrueba extends Form
{
    #[Normalizar(Normalizacion::Identificador)]
    public string $curp = '';

    public function rules(): array
    {
        return ['curp' => ReglasCaptura::curp()];
    }

    public function validationAttributes(): array
    {
        return ['curp' => 'CURP del titular'];
    }
}

class ComponentePrueba extends Component
{
    public string $calle = '';

    public string $correo = '';

    public string $password = '';

    public string $sinRegla = '';

    public ?int $numero = null;

    /** @var list<array{nombre: string}> */
    public array $servicios = [['nombre' => '']];

    public FormularioPrueba $formulario;

    protected function rules(): array
    {
        return [
            'calle' => ['nullable', 'string', 'max:150'],
            'correo' => ReglasCaptura::correo(),
            'servicios.*.nombre' => ['nullable', 'string', 'max:200'],
            'numero' => ['nullable', 'integer'],
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
