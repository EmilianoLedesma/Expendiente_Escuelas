<?php

namespace App\Livewire\Sedeq;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\DTO\FilaTramite;
use App\Application\Revision\ListarColaDelRevisor;
use App\Livewire\Concerns\NotificaToast;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Cola del revisor (opción A): sus trámites por prioridad a la izquierda, el elegido a la derecha.
 * Solo lo asignado a él: ListarColaDelRevisor filtra; aquí solo se presenta. Hallazgos y decisiones: proyecto 2.
 */
#[Layout('layouts.tramite', ['title' => 'Mis Revisiones'])]
class ColaRevisor extends Component
{
    use NotificaToast;

    #[Locked]
    public ?int $seleccionado = null;

    public function mount(): void
    {
        $this->seleccionado = $this->cola()[0]->escuelaNivelId ?? null;
    }

    /** Un trámite fuera de su cola (p. ej. el supervisor lo reasignó con la página abierta) se ignora con aviso. */
    public function seleccionar(int $escuelaNivelId): void
    {
        if ($this->buscar($this->cola(), $escuelaNivelId) === null) {
            $this->toast('Ese trámite ya no está en tu cola', null, 'error');

            return;
        }

        $this->seleccionado = $escuelaNivelId;
    }

    /** @return list<FilaTramite> */
    private function cola(): array
    {
        try {
            return app(ListarColaDelRevisor::class)->ejecutar((int) auth()->id());
        } catch (PrecondicionIncumplida $e) {
            abort_if($e->etapaFaltante === 'rol', 403);
            throw $e;
        }
    }

    /** @param list<FilaTramite> $filas */
    private function buscar(array $filas, ?int $id): ?FilaTramite
    {
        return collect($filas)->firstWhere('escuelaNivelId', $id);
    }

    public function render()
    {
        $filas = $this->cola();

        return view('livewire.sedeq.cola-revisor', [
            'filas' => $filas,
            // Si el elegido salió de la cola, el panel muestra el primero.
            'actual' => $this->buscar($filas, $this->seleccionado) ?? ($filas[0] ?? null),
            'limite' => (int) config('sedeq.revision.limite_por_revisor'),
        ]);
    }
}
