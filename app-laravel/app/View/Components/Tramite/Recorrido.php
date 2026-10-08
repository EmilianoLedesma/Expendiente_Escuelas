<?php

namespace App\View\Components\Tramite;

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

    public function __construct(
        ?ResumenTramiteDTO $resumen = null,
        ?int $escuelaId = null,
        public readonly ?int $escuelaNivelId = null,
        public readonly ?string $seccionActual = null,
    ) {
        $this->resumen = $resumen
            ?? app(ResumenTramite::class)->paraEscuela($escuelaId ?? throw new InvalidArgumentException('Recorrido requiere resumen o escuelaId.'), auth()->id());
    }

    public function render(): View
    {
        return view('components.tramite.recorrido');
    }
}
