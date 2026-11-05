<?php

namespace App\Filament\Concerns;

use App\Application\Revision\RolesSedeq;

/**
 * Recursos y páginas del panel que solo abre un admin. El panel ya exige el rol (User::canAccessPanel);
 * esto lo repite en cada recurso para que un segundo panel o un cambio de esa regla no abra nada.
 */
trait SoloAdmin
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(RolesSedeq::ADMIN) === true;
    }
}
