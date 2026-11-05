<?php

namespace App\Listeners;

use App\Application\Archivo\TramiteCerradoPorAdmin;
use App\Models\Escuela;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\User;
use App\Notifications\TramiteCerrado;

/** Avisa al dueño que su trámite quedó cerrado. Solo el estado: ni quién ni por qué (eso queda en la bitácora). */
class NotificarTramiteCerrado
{
    public function handle(TramiteCerradoPorAdmin $evento): void
    {
        $escuela = Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->with('ternasNombres')->findOrFail($evento->escuelaId);
        $duenoId = (int) Solicitante::whereKey($escuela->solicitante_id)->value('user_id');
        $nombre = $escuela->nombre_aprobado ?? $escuela->ternasNombres->sortBy('numero_propuesta')->first()?->nombre_propuesto;

        User::findOrFail($duenoId)->notify(new TramiteCerrado($nombre));
    }
}
