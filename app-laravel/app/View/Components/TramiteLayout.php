<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/** Shell del trámite para las páginas que no son Livewire (Mis trámites, Resumen del trámite). */
class TramiteLayout extends Component
{
    public function __construct(public ?string $title = null) {}

    public function render(): View
    {
        return view('layouts.tramite');
    }
}
