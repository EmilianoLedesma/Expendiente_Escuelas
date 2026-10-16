<?php

namespace App\Application\Revision;

use App\Application\Revision\DTO\FilaTramite;
use App\Models\User;

/** Cola del revisor: solo sus niveles en revisión, prioridad primero y luego el más antiguo. */
final class ListarColaDelRevisor
{
    /** @return list<FilaTramite> */
    public function ejecutar(int $actorId): array
    {
        RolesSedeq::exigir(User::findOrFail($actorId), RolesSedeq::REVISOR);

        return ListarTablero::filas($actorId);
    }
}
