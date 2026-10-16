<?php

namespace App\Application\Revision;

use App\Models\User;

/** Página de inicio de cada rol: única fuente para login, verificación, confirmación de contraseña, dashboard y /tramite. */
final class RutaInicio
{
    public static function para(User $usuario): string
    {
        return match (true) {
            $usuario->hasRole('sedeq') => '/admin',
            $usuario->hasRole(RolesSedeq::SUPERVISOR) => route('sedeq.tablero', absolute: false),
            $usuario->hasRole(RolesSedeq::REVISOR) => route('sedeq.cola', absolute: false),
            default => route('tramite.index', absolute: false),
        };
    }
}
