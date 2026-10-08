<?php

namespace App\Application\ResponsablesNivel;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\ResponsableNivel;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Quita un acceso. La cuenta se elimina solo si era el último: lo capturado
 * cuelga de escuela_niveles, no del usuario, así que permanece (spec §1, decisión 6).
 * Un trámite enviado no cambia de responsables (WS-7a), igual que InvitarResponsableNivel.
 */
class RevocarResponsableNivel
{
    public function __construct(private readonly TramiteEditable $tramiteEditable = new TramiteEditable) {}

    /**
     * @throws AuthorizationException si el acceso es de un nivel de otro solicitante.
     * @throws PrecondicionIncumplida si el trámite del nivel ya se envió.
     */
    public function ejecutar(int $solicitanteId, int $responsableNivelId): void
    {
        DB::transaction(function () use ($solicitanteId, $responsableNivelId) {
            // WS-7a, orden escuela_niveles → escuelas: el nivel se bloquea primero (como en
            // InvitarResponsableNivel), luego la guarda bloquea la escuela, y solo después
            // las filas del acceso y del usuario.
            $escuelaNivelId = ResponsableNivel::whereKey($responsableNivelId)->value('escuela_nivel_id');
            $nivel = $escuelaNivelId === null ? null : EscuelaNivel::with('escuela')->lockForUpdate()->find($escuelaNivelId);

            if ($nivel === null) {
                return;
            }
            if ((int) $nivel->escuela->solicitante_id !== $solicitanteId) {
                throw new AuthorizationException;
            }
            $this->tramiteEditable->asegurarEscuela((int) $nivel->escuela_id);

            $acceso = ResponsableNivel::lockForUpdate()->find($responsableNivelId);
            if ($acceso === null) {
                return;
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
