<?php

namespace App\Livewire\Sedeq;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\InvitarRevisor;
use App\Application\Revision\ListarTablero;
use App\Application\Revision\RevocarRevisor;
use App\Application\Revision\RolesSedeq;
use App\Livewire\Concerns\NotificaToast;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Revisores del supervisor: carga de cada uno, invitación y revocación. Presentación pura:
 * las reglas viven en Application/Revision. Una PrecondicionIncumplida de etapa `rol` es un 403.
 */
#[Layout('layouts.tramite', ['cuenta' => true, 'title' => 'Revisores'])]
class Revisores extends Component
{
    use NotificaToast;

    public string $nombre = '';

    public string $correo = '';

    public function mount(): void
    {
        try {
            RolesSedeq::exigir(auth()->user(), RolesSedeq::SUPERVISOR);
        } catch (PrecondicionIncumplida) {
            abort(403);
        }
    }

    /** true = la cuenta quedó lista y el aviso en cola (para x-ui.stateful-button). */
    public function invitar(): bool
    {
        $this->resetErrorBag(['nombre', 'correo']);

        try {
            app(InvitarRevisor::class)->ejecutar((int) auth()->id(), $this->nombre, $this->correo);
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->toastDatosInvalidos();

            return false;
        } catch (PrecondicionIncumplida $e) {
            $this->fallo($e, 'No se envió la invitación');

            return false;
        }

        $this->reset('nombre', 'correo');
        $this->toast('Invitación enviada', 'Le enviamos un correo al revisor.');

        return true;
    }

    public function revocar(int $revisorId): void
    {
        try {
            app(RevocarRevisor::class)->ejecutar((int) auth()->id(), $revisorId);
        } catch (PrecondicionIncumplida $e) {
            $this->fallo($e, 'No se pudo revocar');

            return;
        }

        $this->toast('Revisor revocado', 'Sus trámites regresaron a "Sin asignar".');
    }

    private function fallo(PrecondicionIncumplida $e, string $titulo): void
    {
        abort_if($e->etapaFaltante === 'rol', 403);
        $this->toast($titulo, $e->getMessage(), 'error');
    }

    public function render(ListarTablero $listar)
    {
        try {
            $tablero = $listar->ejecutar((int) auth()->id());
        } catch (PrecondicionIncumplida $e) {
            abort_if($e->etapaFaltante === 'rol', 403);
            throw $e;
        }

        // Las filas ya vienen en orden de prioridad y antigüedad; agrupar conserva ese orden.
        $filasPorRevisor = collect($tablero['filas'])->whereNotNull('revisorId')->groupBy('revisorId')->all();

        return view('livewire.sedeq.revisores', ['revisores' => $tablero['revisores'], 'filasPorRevisor' => $filasPorRevisor]);
    }
}
