<?php

namespace App\Policies;

use App\Application\Escuelas\VerificarPropietarioEscuelaNivel;
use App\Models\EscuelaNivel;
use App\Models\User;

/**
 * Nota de simplificación MVP (misma que EscuelaPolicy, repetida aquí a
 * propósito para que quien lea esta clase de forma aislada la vea sin
 * tener que ir a buscar la otra primero): `view` hoy equivale a "es dueño
 * de la escuela padre" — correcto mientras el único consumidor es el
 * wizard del solicitante; deberá dejar de conflar ambos casos en cuanto
 * el panel SEDEQ necesite `view` sin ser dueño.
 */
class EscuelaNivelPolicy
{
    public function __construct(private readonly VerificarPropietarioEscuelaNivel $verificar) {}

    public function view(User $user, EscuelaNivel $escuelaNivel): bool
    {
        if ($user->solicitante === null) {
            return false;
        }

        return $this->verificar->ejecutar($escuelaNivel->getKey(), $user->solicitante->getKey());
    }
}
