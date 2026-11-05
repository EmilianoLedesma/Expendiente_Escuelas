<?php

namespace App\Application\Auth;

use Illuminate\Support\Facades\DB;

/**
 * Cierra las sesiones guardadas de un usuario (driver `database`): al cambiar o restablecer la
 * contraseña, un equipo que ya tenía la sesión abierta deja de entrar. `$conservar` es la sesión
 * actual, que sigue abierta; sin ella se cierran todas.
 */
class CerrarOtrasSesiones
{
    public function ejecutar(int $userId, ?string $conservar = null): void
    {
        DB::table('sessions')
            ->where('user_id', $userId)
            ->when($conservar !== null, fn ($q) => $q->where('id', '!=', $conservar))
            ->delete();
    }
}
