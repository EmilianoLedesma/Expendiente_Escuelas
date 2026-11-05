<?php

namespace App\Listeners;

use App\Application\Revision\RevisionAprobada;
use App\Application\Tramite\TramiteEnviado;
use App\Models\Escuela;
use App\Models\Solicitante;
use App\Models\User;
use App\Notifications\RevisionSinObservaciones;
use App\Notifications\TramiteEnRevision;

/** Avisa al dueño dos hitos: el trámite entró a revisión y la revisión terminó sin observaciones. Solo el dueño. */
class NotificarAvanceDeTramite
{
    public function handleEnviado(TramiteEnviado $evento): void
    {
        [$dueno, $nombre] = $this->duenoYNombre($evento->escuelaId);
        $dueno->notify(new TramiteEnRevision($evento->escuelaId, $nombre));
    }

    public function handleAprobada(RevisionAprobada $evento): void
    {
        [$dueno, $nombre] = $this->duenoYNombre($evento->escuelaId);
        $dueno->notify(new RevisionSinObservaciones($evento->escuelaId, $nombre));
    }

    /** @return array{0: User, 1: ?string} */
    private function duenoYNombre(int $escuelaId): array
    {
        $escuela = Escuela::with('ternasNombres')->findOrFail($escuelaId);
        $duenoId = (int) Solicitante::whereKey($escuela->solicitante_id)->value('user_id');
        $nombre = $escuela->nombre_aprobado ?? $escuela->ternasNombres->sortBy('numero_propuesta')->first()?->nombre_propuesto;

        return [User::findOrFail($duenoId), $nombre];
    }
}
