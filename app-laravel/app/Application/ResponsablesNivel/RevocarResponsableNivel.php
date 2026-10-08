<?php

namespace App\Application\ResponsablesNivel;

use App\Models\ResponsableNivel;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Quita un acceso. La cuenta se elimina solo si era el último: lo capturado
 * cuelga de escuela_niveles, no del usuario, así que permanece (spec §1, decisión 6).
 */
class RevocarResponsableNivel
{
    /** @throws AuthorizationException si el acceso es de un nivel de otro solicitante. */
    public function ejecutar(int $solicitanteId, int $responsableNivelId): void
    {
        DB::transaction(function () use ($solicitanteId, $responsableNivelId) {
            $acceso = ResponsableNivel::with('escuelaNivel.escuela')->lockForUpdate()->find($responsableNivelId);

            if ($acceso === null) {
                return;
            }
            if ((int) $acceso->escuelaNivel->escuela->solicitante_id !== $solicitanteId) {
                throw new AuthorizationException;
            }

            // Serializa con una invitación o revocación concurrente del mismo usuario.
            $usuario = User::whereKey($acceso->user_id)->lockForUpdate()->first();
            $acceso->delete();

            if (! ResponsableNivel::where('user_id', $usuario->id)->exists()) {
                $usuario->delete();
            }
        });
    }
}
