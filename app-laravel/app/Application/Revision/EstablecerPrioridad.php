<?php

namespace App\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EnviarTramite;
use App\Models\EscuelaNivel;
use App\Models\PrioridadRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** El supervisor fija a mano la prioridad (alta, media o baja) de un nivel en revisión. */
class EstablecerPrioridad
{
    /** @throws DatosInvalidos|PrecondicionIncumplida */
    public function ejecutar(int $actorId, int $escuelaNivelId, string $prioridad): void
    {
        if (! in_array($prioridad, EstadoRevision::PRIORIDADES, true)) {
            throw new DatosInvalidos(['prioridad' => 'La prioridad debe ser alta, media o baja.']);
        }

        DB::transaction(function () use ($actorId, $escuelaNivelId, $prioridad) {
            RolesSedeq::exigir(User::findOrFail($actorId), RolesSedeq::SUPERVISOR);

            $estado = EscuelaNivel::whereKey($escuelaNivelId)->lockForUpdate()->value('estado_id');
            $enRevision = EstadoRevision::idEnRevision();
            if ($enRevision === null || (int) $estado !== $enRevision) {
                throw new PrecondicionIncumplida(EnviarTramite::EN_REVISION, 'Solo se puede priorizar un trámite en revisión.');
            }

            PrioridadRevision::updateOrCreate(
                ['escuela_nivel_id' => $escuelaNivelId],
                ['prioridad' => $prioridad, 'actualizado_por' => $actorId],
            );
        });
    }
}
