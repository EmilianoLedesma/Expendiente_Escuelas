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

    /** $slug: la sección del panel desde donde se decide (todas están en el DOM); sin él, la abierta en el servidor. */
    public function aceptar(?string $slug = null): void
    {
        $this->decidir('aceptado', 'Sección aceptada', $slug);
    }

    public function rechazar(?string $slug = null): void
    {
        $this->decidir('rechazado', 'Sección rechazada', $slug);
    }

    private function decidir(string $decision, string $aviso, ?string $slug): void
    {
        if ($slug !== null && SeccionesProyectoEducativo::existe($slug)) {
            $this->seccionAbierta = $slug;
        }

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

        // Las 20 secciones van en el DOM (la pestaña activa se cambia en el cliente, sin viajes al servidor).
        $contenidos = [];
        foreach ($revision['secciones'] as $s) {
            $contenidos[$s['slug']] = app(ContenidoSeccionProyecto::class)->ejecutar((int) auth()->id(), $this->escuelaNivelId, $s['slug']);
        }

        return view('livewire.sedeq.revision-proyecto', [
            'revision' => $revision,
            'contenidos' => $contenidos,
            'inicial' => (collect($revision['secciones'])->firstWhere('slug', $this->seccionAbierta) ?? $revision['secciones'][0])['numero'],
            'volver' => RutaInicio::para(auth()->user()),
        ]);
    }
}
