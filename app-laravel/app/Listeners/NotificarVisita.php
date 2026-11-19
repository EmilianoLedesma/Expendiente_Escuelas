<?php

namespace App\Listeners;

use App\Application\Tramite\ResumenTramite;
use App\Application\Visita\ActaDeVisitaRegistrada;
use App\Application\Visita\VisitaProgramada;
use App\Models\Escuela;
use App\Models\Solicitante;
use App\Models\User;
use App\Models\VisitaInspeccion;
use App\Notifications\ResultadoDeVisita;
use App\Notifications\VisitaProgramadaAviso;

/** Avisa al dueño de la escuela (solo a él) cuando se programa una visita y cuando se registra su acta. */
class NotificarVisita
{
    public function handleProgramada(VisitaProgramada $evento): void
    {
        [$visita, $dueno, $nombre, $nivel] = $this->datos($evento->visitaId);
        $dueno->notify(new VisitaProgramadaAviso(
            (int) $visita->nivel->escuela_id, $nombre, $nivel, $visita->programada_para, ResumenTramite::domicilio($visita->nivel->escuela->plantel), (int) $visita->escuela_nivel_id,
        ));
    }

    public function handleActa(ActaDeVisitaRegistrada $evento): void
    {
        [$visita, $dueno, $nombre, $nivel] = $this->datos($evento->visitaId);
        $dueno->notify(new ResultadoDeVisita(
            (int) $visita->nivel->escuela_id, $nombre, $nivel, (string) $visita->resultado, $visita->observaciones->count(),
            $visita->subsanar_hasta, (int) $visita->escuela_nivel_id,
        ));
    }

    /** @return array{0: VisitaInspeccion, 1: User, 2: ?string, 3: string} */
    private function datos(int $visitaId): array
    {
        $visita = VisitaInspeccion::with(['observaciones', 'nivel.nivelEducativo', 'nivel.escuela.plantel'])->findOrFail($visitaId);
        $escuela = Escuela::with('ternasNombres')->findOrFail($visita->nivel->escuela_id);
        $duenoId = (int) Solicitante::whereKey($escuela->solicitante_id)->value('user_id');
        $nombre = $escuela->nombre_aprobado ?? $escuela->ternasNombres->sortBy('numero_propuesta')->first()?->nombre_propuesto;

        return [$visita, User::findOrFail($duenoId), $nombre, $visita->nivel->nivelEducativo->nombre];
    }
}
