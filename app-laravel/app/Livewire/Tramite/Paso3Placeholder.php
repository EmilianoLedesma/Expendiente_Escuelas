<?php

namespace App\Livewire\Tramite;

use App\Models\EscuelaNivel;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Aterrizaje post-Paso 2 — placeholder hasta que Paso 3 se diseñe.
 * Componente Livewire real (no Route::view) para que {escuelaNivel} se
 * resuelva por binding implícito y `can:view,escuelaNivel` reciba un
 * modelo tipado, no un id crudo.
 */
#[Layout('layouts.tramite')]
class Paso3Placeholder extends Component
{
    public EscuelaNivel $escuelaNivel;

    public function mount(EscuelaNivel $escuelaNivel): void
    {
        $this->escuelaNivel = $escuelaNivel;
    }

    public function render()
    {
        return view('livewire.tramite.paso3-placeholder');
    }
}
