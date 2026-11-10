<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\ProyectoEducativo\FilosofiaProyectoCapturada;
use App\Application\ProyectoEducativo\PresentacionProyectoCapturada;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\ResumenTramite;
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

    public function render(PresentacionProyectoCapturada $presentacion, FilosofiaProyectoCapturada $filosofia)
    {
        return view('livewire.tramite.paso3.proyecto-educativo-indice', [
            'encabezado' => ResumenTramite::encabezado(SeccionesProyectoEducativo::CLAVE_PASO, $this->escuelaNivel->nivelEducativo),
            'secciones' => SeccionesProyectoEducativo::todas(),
            'completas' => [
                SeccionesProyectoEducativo::presentacion() => $presentacion->completa($this->escuelaNivel->id),
                SeccionesProyectoEducativo::filosofia() => $filosofia->completa($this->escuelaNivel->id),
            ],
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => SeccionesProyectoEducativo::CLAVE_PASO]);
    }
}
