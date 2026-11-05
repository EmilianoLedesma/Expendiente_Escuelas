<?php

namespace App\Application\Escuelas;

use App\Models\ResponsableNivel;
use Illuminate\Database\Eloquent\Builder;

/** ¿Tiene este usuario un acceso de responsable? Decisión pura; las Policies la combinan con "es dueño". */
class VerificarAccesoResponsable
{
    public function alNivel(int $userId, int $escuelaNivelId): bool
    {
        return $this->accesosActivos($userId)->where('escuela_nivel_id', $escuelaNivelId)->exists();
    }

    public function aLaEscuela(int $userId, int $escuelaId): bool
    {
        return $this->accesosActivos($userId)
            ->whereHas('escuelaNivel', fn ($q) => $q->where('escuela_id', $escuelaId))
            ->exists();
    }

    /**
     * Una cuenta archivada no tiene acceso aunque conserve su fila de responsable (se restaura con ella).
     *
     * @return Builder<ResponsableNivel>
     */
    private function accesosActivos(int $userId): Builder
    {
        return ResponsableNivel::where('user_id', $userId)
            ->whereHas('user', fn ($q) => $q->whereNull('archivado_at'));
    }
}
