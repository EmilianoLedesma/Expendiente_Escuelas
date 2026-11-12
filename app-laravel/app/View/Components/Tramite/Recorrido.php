<?php

namespace App\View\Components\Tramite;

use App\Application\ProyectoEducativo\EstadoSeccionesProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\DTO\ResumenTramiteDTO;
use App\Application\Tramite\ResumenTramite;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * Recorrido lateral del trámite (Dirección B). Vive en layouts.tramite, que solo se
 * dibuja en la carga inicial de la página — no en cada round-trip de Livewire —, así
 * que la lectura de ResumenTramite ocurre una vez por página. Los componentes Livewire
 * solo pasan ids por layoutData; el hub pasa el DTO que ya construyó. La lectura va
 * por Application (ADR-001); el .blade.php no consulta nada.
 */
class Recorrido extends Component
{
    public readonly ResumenTramiteDTO $resumen;

    /** @var array<int, array<string, bool>> escuela_nivel_id => (slug de sección del proyecto educativo => completa), solo niveles que lo llevan. */
    public readonly array $proyecto;

    public function __construct(
        ?ResumenTramiteDTO $resumen = null,
        ?int $escuelaId = null,
        public readonly ?int $escuelaNivelId = null,
        public readonly ?string $seccionActual = null,
        public readonly ?string $subseccionActual = null,
    ) {
        $this->resumen = $resumen
            ?? app(ResumenTramite::class)->paraEscuela($escuelaId ?? throw new InvalidArgumentException('Recorrido requiere resumen o escuelaId.'), auth()->id());

        $estado = app(EstadoSeccionesProyecto::class);
        $proyecto = [];
        foreach ($this->resumen->niveles as $nivel) {
            if (SeccionesProyectoEducativo::aplicaA($nivel->clave)) {
                $proyecto[$nivel->escuelaNivelId] = $estado->completas($nivel->escuelaNivelId);
            }
        }
        $this->proyecto = $proyecto;
    }

    public function render(): View
    {
        return view('components.tramite.recorrido');
    }
}
