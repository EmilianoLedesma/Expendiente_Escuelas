<?php

namespace App\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\EscuelaNivel;
use App\Models\User;
use App\Models\VisitaInspeccion;
use Illuminate\Support\Facades\DB;

/** El supervisor corrige fecha o inspector de una visita aún programada; el estado del nivel no cambia. */
class ActualizarVisita
{
    /** @throws DatosInvalidos|PrecondicionIncumplida */
    public function ejecutar(User $actor, int $visitaId, \DateTimeInterface $fecha, string $inspector): void
    {
        RolesSedeq::exigir($actor, RolesSedeq::SUPERVISOR);
        [$fecha, $inspector] = DatosVisita::validar($fecha, $inspector);

        DB::transaction(function () use ($visitaId, $fecha, $inspector) {
            $nivelId = VisitaInspeccion::whereKey($visitaId)->valueOrFail('escuela_nivel_id');
            EscuelaNivel::whereKey($nivelId)->lockForUpdate()->firstOrFail();
            $visita = VisitaInspeccion::whereKey($visitaId)->lockForUpdate()->firstOrFail();

            if ($visita->estado !== 'programada') {
                throw new PrecondicionIncumplida('visita', 'Solo se puede editar una visita programada.');
            }

            $visita->update(['programada_para' => $fecha, 'inspector' => $inspector]);
        });
    }
}
