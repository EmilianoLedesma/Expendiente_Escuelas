<?php

namespace Tests\Unit\Livewire\Forms;

use App\Livewire\Forms\PersonaFisicaForm;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class PersonaFisicaFormTest extends TestCase
{
    private function componenteDePrueba(): string
    {
        return (new class extends Component
        {
            public PersonaFisicaForm $form;

            public function validarFormulario(): void
            {
                $this->form->validate();
            }

            public function render()
            {
                return '<div></div>';
            }
        })::class;
    }

    public function test_nombre_vacio_produce_error_de_validacion(): void
    {
        Livewire::test($this->componenteDePrueba())
            ->set('form.nombre', '')
            ->call('validarFormulario')
            ->assertHasErrors(['form.nombre' => 'required']);
    }

    public function test_nombre_valido_no_produce_errores(): void
    {
        Livewire::test($this->componenteDePrueba())
            ->set('form.nombre', 'Juana Pérez')
            ->call('validarFormulario')
            ->assertHasNoErrors();
    }
}
