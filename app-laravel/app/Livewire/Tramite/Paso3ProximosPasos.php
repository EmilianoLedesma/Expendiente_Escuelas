<?php

namespace App\Livewire\Tramite;

use App\Models\EscuelaNivel;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Aterrizaje tras completar los sub-pasos 1-3 de Paso 3. No escribe nada:
 * el paso que lo precede ya marcó su propio avance.
 *
 * `layoutData` es lo que hace que el widget de progreso vea el
 * escuela_nivel_id — Livewire renderiza el layout con los params de
 * PageComponentConfig, no con las propiedades públicas del componente.
 */
#[Layout('layouts.tramite')]
class Paso3ProximosPasos extends Component
{
    public EscuelaNivel $escuelaNivel;

    public function mount(EscuelaNivel $escuelaNivel): void
    {
        $this->escuelaNivel = $escuelaNivel;
    }

    public function render()
    {
        return view('livewire.tramite.paso3-proximos-pasos')
            ->layoutData(['escuelaNivelId' => $this->escuelaNivel->id]);
    }
}
