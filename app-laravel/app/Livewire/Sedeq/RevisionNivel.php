<?php

namespace App\Livewire\Sedeq;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\ConsultarRevision;
use App\Application\Revision\ContenidoPasoRevision;
use App\Application\Revision\DecidirPaso;
use App\Application\Revision\PistasDeRevision;
use App\Application\Revision\RutaInicio;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Livewire\Concerns\NotificaToast;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Revisión de un nivel (opción A): pasos a la izquierda, el paso abierto a la derecha con su decisión.
 * El acceso lo imponen los casos de uso (AuthorizationException => 403); aquí solo se presenta.
 */
#[Layout('layouts.tramite', ['title' => 'Revisión del trámite'])]
class RevisionNivel extends Component
{
    use NotificaToast;

    #[Locked]
    public int $escuelaNivelId;

    /** Solo lo fijan mount() y abrir(): el cliente no puede forzar otro paso. */
    #[Locked]
    public string $pasoAbierto = '';

    public string $motivo = '';

    public function mount(int $escuelaNivel): void
    {
        $this->escuelaNivelId = $escuelaNivel;
        $pasos = $this->revision()['pasos'];
        $this->pasoAbierto = (collect($pasos)->firstWhere('corregido', true) ?? $pasos[0])['clave'];
    }

    /** Solo se abre un paso que pertenezca al nivel; cualquier otra clave se ignora. */
    public function abrir(string $clave): void
    {
        if (in_array($clave, array_column($this->revision()['pasos'], 'clave'), true)) {
            $this->pasoAbierto = $clave;
            $this->motivo = '';
            $this->resetErrorBag();
        }
    }

    public function aceptar(): void
    {
        $this->decidir('aceptado', 'Paso aceptado');
    }

    public function rechazar(): void
    {
        $this->decidir('rechazado', 'Paso rechazado');
    }

    private function decidir(string $decision, string $aviso): void
    {
        try {
            app(DecidirPaso::class)->ejecutar((int) auth()->id(), $this->escuelaNivelId, $this->pasoAbierto, $decision, $decision === 'rechazado' ? $this->motivo : null);
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
        return app(ConsultarRevision::class)->ejecutar((int) auth()->id(), $this->escuelaNivelId);
    }

    /**
     * Pistas del motor por paso: corren una vez por página (persisten entre acciones) porque no cambian
     * mientras el nivel está en revisión; recalcularlas en cada clic reejecutaría todo el motor.
     *
     * Se persisten como arreglos: con un cache que serializa (database) Livewire no recupera objetos
     * FilaValidacion (__PHP_Incomplete_Class) y reejecutaría el motor en cada render.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    #[Computed(persist: true)]
    public function pistas(): array
    {
        return array_map(
            fn (array $filas) => array_map(fn (FilaValidacion $f) => $f->aArreglo(), $filas),
            app(PistasDeRevision::class)->ejecutar((int) auth()->id(), $this->escuelaNivelId),
        );
    }

    public function render()
    {
        $revision = $this->revision();

        return view('livewire.sedeq.revision-nivel', [
            'revision' => $revision,
            // Los corregidos primero; el resto conserva su orden (sortBy es estable).
            'lista' => collect($revision['pasos'])->sortBy(fn (array $p) => $p['corregido'] ? 0 : 1)->values()->all(),
            'paso' => collect($revision['pasos'])->firstWhere('clave', $this->pasoAbierto),
            'contenido' => app(ContenidoPasoRevision::class)->ejecutar((int) auth()->id(), $this->escuelaNivelId, $this->pasoAbierto),
            'pistas' => array_map(FilaValidacion::desdeArreglo(...), $this->pistas[$this->pasoAbierto] ?? []),
            'volver' => RutaInicio::para(auth()->user()),
        ]);
    }
}
