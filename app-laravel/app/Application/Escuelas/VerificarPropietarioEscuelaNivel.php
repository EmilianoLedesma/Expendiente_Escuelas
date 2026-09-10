<?php

namespace App\Application\Escuelas;

use App\Models\EscuelaNivel;

class VerificarPropietarioEscuelaNivel
{
    public function ejecutar(int $escuelaNivelId, int $solicitanteId): bool
    {
        return EscuelaNivel::where('id', $escuelaNivelId)
            ->whereHas('escuela', fn ($q) => $q->where('solicitante_id', $solicitanteId))
            ->exists();
    }
}
