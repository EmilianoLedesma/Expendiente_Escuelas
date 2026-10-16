<?php

namespace App\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Models\Revisor;
use App\Models\User;

/** Roles de la parte SEDEQ del sistema (HUD de revisión). `sedeq` es el administrador de /admin y no opera trámites. */
final class RolesSedeq
{
    public const SUPERVISOR = 'supervisor';

    public const REVISOR = 'revisor';

    /** Cuentas que no pueden eliminarse a sí mismas. */
    public const TODOS = ['sedeq', self::SUPERVISOR, self::REVISOR];

    /** Cuenta que no se borra sola: tiene un rol de SEDEQ o es un revisor ya revocado (su fila en `revisores` la protege). */
    public static function cuentaProtegida(User $usuario): bool
    {
        return $usuario->hasAnyRole(self::TODOS) || Revisor::where('user_id', $usuario->id)->exists();
    }

    public static function esSedeqOperativo(User $usuario): bool
    {
        return $usuario->hasAnyRole([self::SUPERVISOR, self::REVISOR]);
    }

    /** @throws PrecondicionIncumplida */
    public static function exigir(User $actor, string $rol): void
    {
        if (! $actor->hasRole($rol)) {
            throw new PrecondicionIncumplida('rol', 'No tienes permiso para esta acción.');
        }
    }
}
