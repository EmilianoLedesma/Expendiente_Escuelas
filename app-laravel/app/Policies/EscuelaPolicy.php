<?php

namespace App\Policies;

use App\Application\Escuelas\VerificarPropietarioEscuela;
use App\Models\Escuela;
use App\Models\User;

/**
 * Nota de simplificación MVP: `view` hoy es idéntico a "es dueño" —
 * correcto mientras el único consumidor de esta Policy es el wizard del
 * solicitante. En cuanto el panel SEDEQ (Etapa 2, revisor) necesite
 * `view` sin ser dueño, esta Policy debe dejar de conflar ambos casos
 * (p. ej. `view` permite dueño O rol `sedeq`; `update` sigue exigiendo
 * dueño). No implementado aquí a propósito — el panel Filament no
 * consume esta Policy todavía.
 */
class EscuelaPolicy
{
    public function __construct(private readonly VerificarPropietarioEscuela $verificar) {}

    public function view(User $user, Escuela $escuela): bool
    {
        return $this->esDueno($user, $escuela);
    }

    public function update(User $user, Escuela $escuela): bool
    {
        return $this->esDueno($user, $escuela);
    }

    private function esDueno(User $user, Escuela $escuela): bool
    {
        if ($user->solicitante === null) {
            return false;
        }

        return $this->verificar->ejecutar($escuela->getKey(), $user->solicitante->getKey());
    }
}
