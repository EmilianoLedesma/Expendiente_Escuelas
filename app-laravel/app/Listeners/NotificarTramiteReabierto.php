<?php

namespace App\Listeners;

use App\Application\Archivo\TramiteReabiertoPorAdmin;
use App\Models\Escuela;
use App\Models\Solicitante;
use App\Models\User;
use App\Notifications\TramiteReabierto;

/** Avisa al dueño que su trámite volvió a estar disponible. Solo el estado y el enlace; nada interno. */
class NotificarTramiteReabierto
{
    public function handle(TramiteReabiertoPorAdmin $evento): void
    {
        $escuela = Escuela::with('ternasNombres')->findOrFail($evento->escuelaId);
        $duenoId = (int) Solicitante::whereKey($escuela->solicitante_id)->value('user_id');
        $nombre = $escuela->nombre_aprobado ?? $escuela->ternasNombres->sortBy('numero_propuesta')->first()?->nombre_propuesto;

        User::findOrFail($duenoId)->notify(new TramiteReabierto($evento->escuelaId, $nombre));
    }
}
