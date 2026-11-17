<?php

namespace App\Livewire\Sedeq;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Revision\ConsultarRevisionProyecto;
use App\Application\Revision\ContenidoSeccionProyecto;
use App\Application\Revision\DecidirSeccionProyecto;
use App\Application\Revision\RutaInicio;
use App\Livewire\Concerns\NotificaToast;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Revisión del proyecto educativo de un nivel de media superior: las 20 secciones a la izquierda, la abierta a la
 * derecha con su decisión. El evaluador decide; el supervisor solo lee. El acceso lo imponen los casos de uso
 * (AuthorizationException => 403); aquí solo se presenta.
 */
#[Layout('layouts.tramite', ['title' => 'Revisión del proyecto educativo'])]
class RevisionProyecto extends Component
{
    use NotificaToast;

    #[Locked]
    public int $escuelaNivelId;

    /** Slug de la sección abierta; solo lo fijan mount() y abrir(): el cliente no puede forzar otra. */
    #[Locked]
    public string $seccionAbierta = '';

    public string $motivo = '';

    public function mount(int $escuelaNivel): void
    {
        $this->escuelaNivelId = $escuelaNivel;
        $secciones = $this->revision()['secciones'];
        $this->seccionAbierta = (collect($secciones)->firstWhere('estado', 'pendiente') ?? $secciones[0])['slug'];
    }

    /** Solo se abre una sección del proyecto; cualquier otra clave se ignora. */
    public function abrir(string $slug): void
    {
        if (SeccionesProyectoEducativo::existe($slug)) {
            $this->seccionAbierta = $slug;
            $this->motivo = '';
            $this->resetErrorBag();
        }
    }

    public function aceptar(): void
    {
        $this->decidir('aceptado', 'Sección aceptada');
    }

    public function rechazar(): void
    {
        $this->decidir('rechazado', 'Sección rechazada');
    }

    private function decidir(string $decision, string $aviso): void
    {
        try {
            app(DecidirSeccionProyecto::class)->ejecutar(
                (int) auth()->id(),
                $this->escuelaNivelId,
                SeccionesProyectoEducativo::claveRevision($this->seccionAbierta),
                $decision,
                $decision === 'rechazado' ? $this->motivo : null,
            );
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        } catch (PrecondicionIncumplida $e) {
            $this->toast('No se pudo registrar la decisión', $e->getMessage(), 'error');

            return;
        }

        $this->motivo = '';
        $this->resetErrorBag();
        $this->toast($aviso);
    }

    /** @return array<string, mixed> */
    private function revision(): array
    {
        return app(ConsultarRevisionProyecto::class)->ejecutar((int) auth()->id(), $this->escuelaNivelId);
    }

    public function render()
    {
        $revision = $this->revision();

        return view('livewire.sedeq.revision-proyecto', [
            'revision' => $revision,
            'seccion' => collect($revision['secciones'])->firstWhere('slug', $this->seccionAbierta),
            'contenido' => app(ContenidoSeccionProyecto::class)->ejecutar((int) auth()->id(), $this->escuelaNivelId, $this->seccionAbierta),
            'volver' => RutaInicio::para(auth()->user()),
        ]);
    }
}
