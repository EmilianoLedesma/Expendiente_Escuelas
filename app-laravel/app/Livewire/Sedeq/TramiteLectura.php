<?php

namespace App\Livewire\Sedeq;

use App\Application\Revision\ConsultarTramiteSedeq;
use App\Application\Revision\RutaInicio;
use App\Models\Escuela;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Trámite en solo lectura para supervisor/revisor. Sin layoutData de escuela: el layout no pinta el
 * recorrido del solicitante (con enlaces de edición). Sin acciones: nada que escribir.
 */
#[Layout('layouts.tramite', ['title' => 'Trámite (solo lectura)'])]
class TramiteLectura extends Component
{
    #[Locked]
    public int $escuelaId;

    public function mount(Escuela $escuela): void
    {
        $this->escuelaId = $escuela->id;
    }

    public function render(ConsultarTramiteSedeq $consultar)
    {
        return view('livewire.sedeq.tramite-lectura', [
            'tramite' => $consultar->ejecutar((int) auth()->id(), $this->escuelaId),
            'volver' => RutaInicio::para(auth()->user()),
        ]);
    }
}
