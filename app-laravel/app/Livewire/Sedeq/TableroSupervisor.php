<?php

namespace App\Livewire\Sedeq;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\EstablecerPrioridad;
use App\Application\Revision\LimiteDeRevisorAlcanzado;
use App\Application\Revision\ListarTablero;
use App\Application\Revision\RolesSedeq;
use App\Livewire\Concerns\NotificaToast;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Tablero del supervisor (opción A): trámites en revisión y un resumen de carga por revisor; invitar y revocar viven en Revisores.
 * Presentación pura: las reglas (rol, tope, bloqueo) viven en Application/Revision. Una PrecondicionIncumplida
 * de etapa `rol` es un 403; cualquier otra se avisa con un toast.
 */
#[Layout('layouts.tramite', ['title' => 'Tablero del supervisor'])]
class TableroSupervisor extends Component
{
    use NotificaToast;

    /** @var list<string> ids de escuela_niveles marcados (checkboxes) */
    public array $seleccion = [];

    public string $revisorDestino = '';

    /** Aviso de tope alcanzado pendiente de confirmar: ['ids' => list<int>, 'revisorId' => int, 'limite' => int] o []. */
    #[Locked]
    public array $sobrecarga = [];

    /** Cuenta los avisos de tope: la clave del <dialog> cambia y se vuelve a abrir aunque el estado no cambie. */
    public int $avisosSobrecarga = 0;

    public function mount(): void
    {
        $this->exigirRol();
    }

    /** @param array<int|string> $escuelaNivelIds */
    public function asignarSeleccion(array $escuelaNivelIds, int $revisorId, bool $confirmarSobrecarga = false): void
    {
        // Livewire restaura los errores guardados: sin esto, un error viejo sigue marcado tras corregirlo.
        $this->resetErrorBag(['seleccion', 'revisorDestino']);
        $ids = array_values(array_unique(array_map('intval', $escuelaNivelIds)));
        if ($ids === []) {
            $this->addError('seleccion', 'Selecciona al menos un trámite.');

            return;
        }
        if ($revisorId <= 0) {
            $this->addError('revisorDestino', 'Elige el revisor.');

            return;
        }

        try {
            app(AsignarTramite::class)->asignarSeleccion((int) auth()->id(), $ids, $revisorId, $confirmarSobrecarga);
        } catch (LimiteDeRevisorAlcanzado $e) {
            // Una selección mayor que el tope no cabe ni confirmando: ofrecer confirmar sería un ciclo sin salida.
            if (count($ids) > $e->limite) {
                $this->sobrecarga = [];
                $this->addError('seleccion', 'Seleccionaste '.count($ids).'; el tope por revisor es '.$e->limite.'.');

                return;
            }
            $this->sobrecarga = ['ids' => $ids, 'revisorId' => $e->revisorId, 'limite' => $e->limite];
            $this->avisosSobrecarga++;
            $nombre = User::whereKey($e->revisorId)->value('name') ?? 'El revisor';
            $this->toast($nombre.' llegó a su límite de '.$e->limite.' trámites', 'Confirma si quieres asignarle otro de todos modos.', 'info');

            return;
        } catch (PrecondicionIncumplida $e) {
            $this->sobrecarga = [];
            $this->fallo($e, 'No se pudo asignar');

            return;
        }

        $this->reset('seleccion', 'revisorDestino', 'sobrecarga');
        $this->toast('Trámites asignados', count($ids) === 1 ? 'Se asignó 1 trámite.' : 'Se asignaron '.count($ids).' trámites.');
    }

    public function cancelarSobrecarga(): void
    {
        $this->sobrecarga = [];
    }

    public function cambiarPrioridad(int $escuelaNivelId, string $prioridad): void
    {
        try {
            app(EstablecerPrioridad::class)->ejecutar((int) auth()->id(), $escuelaNivelId, $prioridad);
        } catch (DatosInvalidos $e) {
            $this->toast('No se cambió la prioridad', implode(' ', $e->errores), 'error');

            return;
        } catch (PrecondicionIncumplida $e) {
            $this->fallo($e, 'No se cambió la prioridad');

            return;
        }

        $this->toast('Prioridad actualizada');
        // La fila se reordena: la vista devuelve el foco a este select (WCAG 2.4.3).
        $this->dispatch('prioridad-cambiada', id: $escuelaNivelId);
    }

    private function exigirRol(): void
    {
        try {
            RolesSedeq::exigir(auth()->user(), RolesSedeq::SUPERVISOR);
        } catch (PrecondicionIncumplida) {
            abort(403);
        }
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

        $sobrecargado = $this->sobrecarga === [] ? null
            : collect($tablero['revisores'])->firstWhere('revisorId', $this->sobrecarga['revisorId']);

        return view('livewire.sedeq.tablero-supervisor', [
            'filas' => $tablero['filas'],
            'revisores' => $tablero['revisores'],
            'sobrecargado' => $sobrecargado,
        ]);
    }
}
