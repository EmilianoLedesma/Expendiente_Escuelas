<?php

namespace App\Listeners;

use App\Application\Revision\RondaCerrada;
use App\Application\Tramite\ObservacionesDeEscuela;
use App\Models\Escuela;
use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use App\Models\User;
use App\Notifications\ObservacionesEnTramite;

/**
 * Al cerrarse la ronda avisa al dueño (todos los motivos) y a cada responsable por nivel con niveles devueltos
 * (solo los de su nivel). Un correo por destinatario; quien no tenga motivos no recibe nada. Los motivos salen
 * de ObservacionesDeEscuela, que está vacío con la ronda abierta: no se confía solo en el evento.
 */
class NotificarObservaciones
{
    public function __construct(private readonly ObservacionesDeEscuela $observaciones) {}

    public function handle(RondaCerrada $evento): void
    {
        $escuela = Escuela::findOrFail($evento->escuelaId);
        $duenoId = (int) Solicitante::whereKey($escuela->solicitante_id)->value('user_id');

        $destinatarios = ResponsableNivel::whereIn('escuela_nivel_id', $escuela->escuelaNiveles()->pluck('id'))
            ->pluck('user_id')->map(fn ($id) => (int) $id)->push($duenoId)->unique(); // por id: quien es dueño y responsable recibe un solo correo

        foreach ($destinatarios as $usuarioId) {
            $pasos = array_map(
                fn (array $o) => ['nivel' => $o['nivel'], 'titulo' => $o['titulo'], 'motivo' => $o['motivo']],
                $this->observaciones->paraUsuario($evento->escuelaId, $usuarioId),
            );
            if ($pasos !== []) {
                User::findOrFail($usuarioId)->notify(new ObservacionesEnTramite($evento->escuelaId, $pasos));
            }
        }
    }
}
