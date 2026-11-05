<?php

namespace App\Filament\Pages;

use App\Application\Salud\ConsultarSaludSistema;
use Filament\Pages\Page;

/** Pantalla de solo lectura con el estado de los servicios (el panel ya es solo para admin). */
class SaludSistema extends Page
{
    protected static ?string $navigationLabel = 'Estado del sistema';

    protected static ?string $title = 'Estado del sistema';

    protected static ?string $slug = 'salud';

    protected string $view = 'filament.pages.salud-sistema';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['chequeos' => app(ConsultarSaludSistema::class)->ejecutar()];
    }
}
