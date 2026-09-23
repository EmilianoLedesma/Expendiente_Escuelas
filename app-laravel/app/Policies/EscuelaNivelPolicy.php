<?php

namespace App\Policies;

use App\Application\Escuelas\VerificarPropietarioEscuelaNivel;
use App\Models\EscuelaNivel;
use App\Models\User;

/**
 * Misma semántica que EscuelaPolicy (repetida aquí a propósito para quien
 * lea esta clase aislada): `update` autoriza toda página que escribe y es
 * SOLO del dueño de la escuela padre. `view` autoriza lectura pura y hoy
 * también es solo dueño, pero es la habilidad que deberá abrirse a
 * revisores SEDEQ — por eso ninguna ruta que escribe puede usar `view`:
 * abrir `view` nunca debe abrir escrituras. Ambas delegan en
 * VerificarPropietarioEscuelaNivel, sin reimplementar la comparación.
 */
class EscuelaNivelPolicy
{
    public function __construct(private readonly VerificarPropietarioEscuelaNivel $verificar) {}

    public function view(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return $this->esDueno($user, $escuelaNivel);
    }

    public function update(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return $this->esDueno($user, $escuelaNivel);
    }

    private function esDueno(User $user, EscuelaNivel $escuelaNivel): bool
    {
        if ($user->solicitante === null) {
            return false;
        }

        return $this->verificar->ejecutar($escuelaNivel->getKey(), $user->solicitante->getKey());
    }
}
