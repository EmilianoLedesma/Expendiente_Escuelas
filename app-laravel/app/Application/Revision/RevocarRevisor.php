<?php

namespace App\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Models\Revisor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * El supervisor revoca a un revisor: pierde el rol, queda marcado con
 * revocado_at y se le quitan todas sus asignaciones. La cuenta no se borra.
 * Se bloquea primero su fila users (mismo orden que AsignarTramite).
 */
class RevocarRevisor
{
    public function __construct(private readonly DesasignarTramite $desasignar) {}

    /** @throws PrecondicionIncumplida */
    public function ejecutar(int $actorId, int $revisorId): void
    {
        DB::transaction(function () use ($actorId, $revisorId) {
            RolesSedeq::exigir(User::findOrFail($actorId), RolesSedeq::SUPERVISOR);

            $revisor = User::whereKey($revisorId)->lockForUpdate()->first();
            if ($revisor === null || ! $revisor->hasRole(RolesSedeq::REVISOR)) {
                throw new PrecondicionIncumplida(RolesSedeq::REVISOR, 'El usuario elegido no es un revisor activo.');
            }

            $revisor->removeRole(RolesSedeq::REVISOR);
            Revisor::updateOrCreate(['user_id' => $revisorId], ['revocado_at' => now()]);
            $this->desasignar->todosDe($actorId, $revisorId);
        });
    }
}
