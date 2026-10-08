<?php

namespace App\Policies;

use App\Application\Escuelas\VerificarAccesoResponsable;
use App\Application\Escuelas\VerificarPropietarioEscuela;
use App\Application\Tramite\TramiteEditable;
use App\Models\Escuela;
use App\Models\User;

/**
 * `update` autoriza toda página que escribe (Paso 2 responsable y
 * documentos) y es SOLO del dueño. `view` autoriza lectura pura (descarga
 * de documento) y hoy también es solo dueño,
 * pero es la habilidad que deberá abrirse a revisores SEDEQ (Etapa 2:
 * dueño O rol `sedeq`) — por eso ninguna ruta que escribe puede usar
 * `view`: abrir `view` nunca debe abrir escrituras. No abierto aquí a
 * propósito — el panel Filament no consume esta Policy todavía. Ambas
 * delegan en VerificarPropietarioEscuela, sin reimplementar la comparación.
 */
class EscuelaPolicy
{
    public function __construct(
        private readonly VerificarPropietarioEscuela $verificar,
        private readonly VerificarAccesoResponsable $accesoResponsable,
        private readonly TramiteEditable $editable,
    ) {}

    public function view(User $user, Escuela $escuela): bool
    {
        return $this->esDueno($user, $escuela);
    }

    /** Hub "Resumen del trámite": el dueño, o un responsable con algún nivel de esta escuela (el DTO se acota a sus niveles). `view` NO cambia: protege descargas de documentos de la escuela. */
    public function verResumen(User $user, Escuela $escuela): bool
    {
        return $this->esDueno($user, $escuela)
            || $this->accesoResponsable->aLaEscuela($user->getKey(), $escuela->getKey());
    }

    /** Solo el dueño y solo mientras el trámite sea editable (WS-7a): tras el envío toda página que escribe responde 403. */
    public function update(User $user, Escuela $escuela): bool
    {
        return $this->esDueno($user, $escuela) && $this->editable->esEditable($escuela->getKey());
    }

    /**
     * Eliminar el trámite: SOLO el dueño, habilidad propia para que abrir
     * `view` a SEDEQ (o `update` a quien sea) nunca otorgue borrar. La regla
     * "todos los niveles en captura" vive en EliminarTramite, no aquí.
     */
    public function delete(User $user, Escuela $escuela): bool
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
