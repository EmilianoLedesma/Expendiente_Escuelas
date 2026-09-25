<?php

namespace App\View\Components;

use App\Application\Tramite\DTO\ResumenTramiteDTO;
use Illuminate\View\Component;
use Illuminate\View\View;

/** Shell del trámite para las páginas que no son Livewire (Mis trámites, Resumen del trámite). */
class TramiteLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?ResumenTramiteDTO $resumen = null,
        public ?string $seccionActual = null,
    ) {}

    public function render(): View
    {
        return view('layouts.tramite');
    }
}
