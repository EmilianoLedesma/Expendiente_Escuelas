<?php

namespace App\Policies;

use App\Application\Escuelas\VerificarPropietarioEscuela;
use App\Models\Escuela;
use App\Models\User;

/**
 * `update` autoriza toda página que escribe (Paso 2 responsable y
 * documentos) y es SOLO del dueño. `view` autoriza lectura pura (descarga
 * de documento, PDF del Formato de Solicitud) y hoy también es solo dueño,
 * pero es la habilidad que deberá abrirse a revisores SEDEQ (Etapa 2:
 * dueño O rol `sedeq`) — por eso ninguna ruta que escribe puede usar
 * `view`: abrir `view` nunca debe abrir escrituras. No abierto aquí a
 * propósito — el panel Filament no consume esta Policy todavía. Ambas
 * delegan en VerificarPropietarioEscuela, sin reimplementar la comparación.
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
