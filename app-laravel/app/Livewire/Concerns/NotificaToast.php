<?php

namespace App\Livewire\Concerns;

/** Avisa al navegador (x-ui.toast-region escucha el evento `toast`). tipo: success | error | info. */
trait NotificaToast
{
    protected function toast(string $titulo, ?string $descripcion = null, string $tipo = 'success'): void
    {
        $this->dispatch('toast', tipo: $tipo, titulo: $titulo, descripcion: $descripcion);
    }

    /** Un caso de uso rechazó los datos: los errores por campo siguen en pantalla, el aviso solo lo dice. */
    protected function toastDatosInvalidos(): void
    {
        $this->toast('No se guardó', 'Revisa los campos marcados.', 'error');
    }

    /** Para cuando la acción redirige: el aviso se muestra en la página de destino (flash de sesión). */
    protected function toastAlRedirigir(string $titulo, ?string $descripcion = null, string $tipo = 'success'): void
    {
        session()->flash('toast', ['tipo' => $tipo, 'titulo' => $titulo, 'descripcion' => $descripcion]);
    }
}
