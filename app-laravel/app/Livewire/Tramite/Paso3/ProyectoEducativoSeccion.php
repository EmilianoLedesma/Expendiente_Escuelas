<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\ResumenTramite;
use App\Livewire\Concerns\NotificaToast;
use App\Livewire\Tramite\Paso3\Concerns\CompuertaPaso3;
use App\Models\EscuelaNivel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Una sección de la Presentación del Proyecto Educativo: por ahora solo el marcador "Contenido por definir". */
#[Layout('layouts.tramite')]
class ProyectoEducativoSeccion extends Component
{
    use CompuertaPaso3;
    use NotificaToast;

    #[Locked]
    public EscuelaNivel $escuelaNivel;

    #[Locked]
    public string $seccion = '';

    public function mount(EscuelaNivel $escuelaNivel, string $seccion): void
    {
        $this->escuelaNivel = $escuelaNivel;
        $this->seccion = $seccion;

        abort_unless(SeccionesProyectoEducativo::aplicaA($escuelaNivel->nivelEducativo->clave), 404);
        abort_unless(app(EstadoPaso3::class)->tieneEnCatalogo($escuelaNivel->id, SeccionesProyectoEducativo::CLAVE_PASO), 404);
        abort_unless(SeccionesProyectoEducativo::existe($seccion), 404);

        $this->redirigirSiNoAlcanzable($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);
    }

    public function render()
    {
        $slugs = array_keys(SeccionesProyectoEducativo::todas());
        $indice = (int) array_search($this->seccion, $slugs, true);

        return view('livewire.tramite.paso3.proyecto-educativo-seccion', [
            'encabezado' => ResumenTramite::encabezado(SeccionesProyectoEducativo::CLAVE_PASO, $this->escuelaNivel->nivelEducativo),
            'numero' => $indice + 1,
            'nombre' => SeccionesProyectoEducativo::nombre($this->seccion),
            'anterior' => isset($slugs[$indice - 1]) ? ['slug' => $slugs[$indice - 1], 'nombre' => SeccionesProyectoEducativo::nombre($slugs[$indice - 1])] : null,
            'siguiente' => isset($slugs[$indice + 1]) ? ['slug' => $slugs[$indice + 1], 'nombre' => SeccionesProyectoEducativo::nombre($slugs[$indice + 1])] : null,
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => SeccionesProyectoEducativo::CLAVE_PASO]);
    }
}
