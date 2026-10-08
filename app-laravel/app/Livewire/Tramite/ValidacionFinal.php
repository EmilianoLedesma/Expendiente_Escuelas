<?php

namespace App\Livewire\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EnviarTramite;
use App\Application\Tramite\TramiteEditable;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Application\Validaciones\UltimaValidacionFinal;
use App\Models\Escuela;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Last step of the wizard (ADR-007): runs the final validation, stores it as a
 * PDF and points at each thing to fix; when nothing blocks, offers "Enviar a
 * SEDEQ" (WS-7a). Presentation only — the ready rule lives in
 * EjecutarValidacionFinal and the send in EnviarTramite, which re-evaluates
 * instead of trusting what this page shows. Only the owner reaches enviar():
 * the route's can:update,escuela is re-applied on every Livewire round trip,
 * and a responsable del nivel never holds it (ADR-015).
 */
#[Layout('layouts.tramite')]
class ValidacionFinal extends Component
{
    public Escuela $escuela;

    /** A GET must not write: it validates only the first time (or over a pre-capacity row); later runs are the "validar de nuevo" action. */
    public function mount(Escuela $escuela, EjecutarValidacionFinal $ejecutarValidacionFinal, UltimaValidacionFinal $ultimaValidacionFinal): void
    {
        $this->escuela = $escuela;

        if ($ultimaValidacionFinal->faltaParaEscuela($escuela->id)) {
            $this->validar($ejecutarValidacionFinal);
        }
    }

    public function validarDeNuevo(EjecutarValidacionFinal $ejecutarValidacionFinal): void
    {
        $this->validar($ejecutarValidacionFinal);
    }

    public function enviar(EnviarTramite $enviarTramite): void
    {
        try {
            $enviarTramite->ejecutar($this->escuela->id, (int) auth()->id());
        } catch (PrecondicionIncumplida $e) {
            // The page may show an older "lista" result: point at re-validating, except when already sent.
            $this->addError('envio', $e->etapaFaltante === TramiteEditable::ENVIADO
                ? $e->getMessage()
                : $e->getMessage().' Pulsa «Validar de nuevo» para actualizar el resultado.');

            return;
        }

        session()->flash('status', 'Trámite enviado a SEDEQ.');
        $this->redirectRoute('tramite.resumen', ['escuela' => $this->escuela->id]);
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
