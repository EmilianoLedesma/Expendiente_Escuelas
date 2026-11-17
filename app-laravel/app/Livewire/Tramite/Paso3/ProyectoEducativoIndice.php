<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\ConfirmarProyectoEducativo;
use App\Application\ProyectoEducativo\EstadoSeccionesProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\ObservacionesDeEscuela;
use App\Application\Tramite\ResumenTramite;
use App\Application\Tramite\TramiteEditable;
use App\Livewire\Concerns\NotificaToast;
use App\Livewire\Tramite\Paso3\Concerns\CompuertaPaso3;
use App\Models\EscuelaNivel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Paso 3, sub-paso 7 (solo media superior): índice de las secciones de la
 * Presentación del Proyecto Educativo. Solo la primera sección tiene contenido
 * (lleva su etiqueta Completa/Pendiente); el paso no se completa desde aquí.
 */
#[Layout('layouts.tramite')]
class ProyectoEducativoIndice extends Component
{
    use CompuertaPaso3;
    use NotificaToast;

    #[Locked]
    public EscuelaNivel $escuelaNivel;

    public function mount(EscuelaNivel $escuelaNivel): void
    {
        $this->escuelaNivel = $escuelaNivel;

        abort_unless(SeccionesProyectoEducativo::aplicaA($escuelaNivel->nivelEducativo->clave), 404);
        abort_unless(app(EstadoPaso3::class)->tieneEnCatalogo($escuelaNivel->id, SeccionesProyectoEducativo::CLAVE_PASO), 404);

        $this->redirigirSiNoAlcanzable($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);
    }

    /** Confirma el paso con las 20 secciones completas; el caso de uso decide, aquí solo se avisa. */
    public function confirmar(): void
    {
        try {
            app(ConfirmarProyectoEducativo::class)->ejecutar($this->escuelaNivel->id);
        } catch (PrecondicionIncumplida $e) {
            $this->toast('No se pudo confirmar', $e->getMessage(), 'error');

            return;
        }

        $this->toast('Proyecto educativo confirmado', 'El paso quedó completo.');
    }

    public function render(EstadoSeccionesProyecto $estado)
    {
        $completas = $estado->completas($this->escuelaNivel->id);
        $confirmado = app(EstadoPaso3::class)->completado($this->escuelaNivel->id, SeccionesProyectoEducativo::CLAVE_PASO);

        return view('livewire.tramite.paso3.proyecto-educativo-indice', [
            'encabezado' => ResumenTramite::encabezado(SeccionesProyectoEducativo::CLAVE_PASO, $this->escuelaNivel->nivelEducativo),
            'secciones' => SeccionesProyectoEducativo::todas(),
            'completas' => $completas,
            'confirmado' => $confirmado,
            // Tras una devolución: secciones que el evaluador rechazó (slug => motivo).
            'rechazadas' => app(ObservacionesDeEscuela::class)->seccionesRechazadas($this->escuelaNivel->id),
            // El botón solo aparece con las 20 completas, sin confirmar y con el trámite todavía editable.
            'puedeConfirmar' => ! $confirmado
                && ! in_array(false, $completas, true)
                && app(TramiteEditable::class)->esEditable($this->escuelaNivel->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $this->escuelaNivel->id),
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => SeccionesProyectoEducativo::CLAVE_PASO]);
    }
}
