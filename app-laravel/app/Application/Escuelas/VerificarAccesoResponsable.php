<?php

namespace App\Application\Escuelas;

use App\Models\ResponsableNivel;

/** ¿Tiene este usuario un acceso de responsable? Decisión pura; las Policies la combinan con "es dueño". */
class VerificarAccesoResponsable
{
    public function alNivel(int $userId, int $escuelaNivelId): bool
    {
        return ResponsableNivel::where('user_id', $userId)->where('escuela_nivel_id', $escuelaNivelId)->exists();
    }

    public function aLaEscuela(int $userId, int $escuelaId): bool
    {
        return ResponsableNivel::where('user_id', $userId)
            ->whereHas('escuelaNivel', fn ($q) => $q->where('escuela_id', $escuelaId))
            ->exists();
    }
}
