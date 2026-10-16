<?php

namespace App\Policies;

use App\Application\Escuelas\VerificarAccesoResponsable;
use App\Application\Escuelas\VerificarPropietarioEscuela;
use App\Application\Revision\AccesoSedeq;
use App\Application\Tramite\TramiteEditable;
use App\Models\Escuela;
use App\Models\User;

/**
 * `update` autoriza toda página que escribe (Paso 2 responsable y
 * documentos) y es SOLO del dueño. `view` autoriza lectura pura (descarga
 * de documento, vista SEDEQ) y admite al dueño o a SEDEQ
 * (supervisor/revisor, AccesoSedeq) — por eso ninguna ruta que escribe puede
 * usar `view`: abrir `view` nunca abre escrituras. El rol `sedeq` (admin) no
 * entra. La propiedad se delega en VerificarPropietarioEscuela.
 */
class EscuelaPolicy
{
    public function __construct(
        private readonly VerificarPropietarioEscuela $verificar,
        private readonly VerificarAccesoResponsable $accesoResponsable,
        private readonly TramiteEditable $editable,
        private readonly AccesoSedeq $accesoSedeq,
    ) {}

    public function view(User $user, Escuela $escuela): bool
    {
        return $this->esDueno($user, $escuela) || $this->accesoSedeq->puedeVerEscuela($user, $escuela->getKey());
    }

    /**
     * Reporte de validación final: SOLO el dueño. Cubre todos los niveles de la escuela y también se guarda
     * en cada corrida previa al envío; SEDEQ no lo ve en el proyecto 1 (el proyecto 2 lo abrirá acotado por
     * nivel y solo para la evaluación enviada).
     */
    public function verReporte(User $user, Escuela $escuela): bool
    {
        return $this->esDueno($user, $escuela);
    }

    /** Hub "Resumen del trámite": el dueño, o un responsable con algún nivel de esta escuela (el DTO se acota a sus niveles). SEDEQ no entra: el hub trae enlaces de edición; SEDEQ lee por `sedeq.tramite` (`view`). */
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
