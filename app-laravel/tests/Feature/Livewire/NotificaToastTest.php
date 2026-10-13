<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Concerns\NotificaToast;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class NotificaToastTest extends TestCase
{
    private function componente(): Component
    {
        return new class extends Component
        {
            use NotificaToast;

            public function exito(): void
            {
                $this->toast('Guardado', 'Todo listo');
            }

            public function fallo(): void
            {
                $this->toast('No se pudo', null, 'error');
            }

            public function conRedireccion(): void
            {
                $this->toastAlRedirigir('Guardado', 'Todo listo');
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };
    }

    public function test_dispara_el_evento_toast_de_exito_por_defecto(): void
    {
        Livewire::test($this->componente())
            ->call('exito')
            ->assertDispatched('toast', tipo: 'success', titulo: 'Guardado', descripcion: 'Todo listo');
    }

    public function test_toast_al_redirigir_lo_deja_en_la_sesion_para_la_pagina_siguiente(): void
    {
        $this->componente()->conRedireccion();

        $this->assertSame(['tipo' => 'success', 'titulo' => 'Guardado', 'descripcion' => 'Todo listo'], session('toast'));
    }

    public function test_dispara_el_evento_toast_con_el_tipo_indicado(): void
    {
        Livewire::test($this->componente())
            ->call('fallo')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se pudo', descripcion: null);
    }
}
