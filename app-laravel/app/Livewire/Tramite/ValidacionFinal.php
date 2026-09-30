<?php

namespace App\Livewire\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Application\Validaciones\UltimaValidacionFinal;
use App\Models\Escuela;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Last step of the wizard (ADR-007): runs the documental validation over
 * everything uploaded, stores it as a PDF and points at each document to
 * fix. Presentation only — the rules and the send gate live in
 * EjecutarValidacionFinal.
 */
#[Layout('layouts.tramite')]
class ValidacionFinal extends Component
{
    public Escuela $escuela;

    public function mount(Escuela $escuela, EjecutarValidacionFinal $ejecutarValidacionFinal): void
    {
        $this->escuela = $escuela;
        $this->validar($ejecutarValidacionFinal);
    }

    public function validarDeNuevo(EjecutarValidacionFinal $ejecutarValidacionFinal): void
    {
        $this->validar($ejecutarValidacionFinal);
    }

    private function validar(EjecutarValidacionFinal $ejecutarValidacionFinal): void
    {
        try {
            $ejecutarValidacionFinal->ejecutar($this->escuela->id);
        } catch (PrecondicionIncumplida) {
            $this->redirectRoute('tramite.resumen', ['escuela' => $this->escuela->id]);
        }
    }

    public function render(UltimaValidacionFinal $ultimaValidacionFinal)
    {
        return view('livewire.tramite.validacion-final', [
            'validacion' => $ultimaValidacionFinal->paraEscuela($this->escuela->id),
        ])->layoutData(['escuelaId' => $this->escuela->id, 'seccionActual' => 'validacion']);
    }
}
