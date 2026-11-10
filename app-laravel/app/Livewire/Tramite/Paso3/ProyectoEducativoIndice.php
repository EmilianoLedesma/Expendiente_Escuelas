<?php

namespace App\Livewire\Tramite\Paso3;

use App\Models\EscuelaNivel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Provisional: la tarea de las páginas lo reemplaza. Solo permite que la ruta exista. */
#[Layout('layouts.tramite')]
class ProyectoEducativoIndice extends Component
{
    #[Locked]
    public EscuelaNivel $escuelaNivel;

    public function mount(EscuelaNivel $escuelaNivel): void
    {
        $this->escuelaNivel = $escuelaNivel;
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
