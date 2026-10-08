<?php

namespace App\Policies;

use App\Application\Escuelas\VerificarAccesoResponsable;
use App\Application\Escuelas\VerificarPropietarioEscuelaNivel;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\User;

/**
 * `view` (lectura pura) y `update` (toda página del nivel que escribe)
 * admiten al dueño de la escuela padre O al responsable asignado a ESTE
 * nivel (ADR-015). `updateInmueble` y `gestionarResponsables` son solo del
 * dueño. Ninguna ruta que escribe puede apoyarse en `view`: es la habilidad
 * que deberá abrirse a revisores SEDEQ, y abrirla nunca debe abrir
 * escrituras. La propiedad se delega en VerificarPropietarioEscuelaNivel y
 * la asignación en VerificarAccesoResponsable, sin reimplementar comparaciones.
 * Las tres habilidades que escriben exigen además que el trámite de la
 * escuela padre siga editable (WS-7a, TramiteEditable): tras el envío, 403.
 */
class EscuelaNivelPolicy
{
    public function __construct(
        private readonly VerificarPropietarioEscuelaNivel $verificar,
        private readonly VerificarAccesoResponsable $accesoResponsable,
        private readonly TramiteEditable $editable,
    ) {}

    public function view(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return $this->esDueno($user, $escuelaNivel) || $this->esResponsable($user, $escuelaNivel);
    }

    public function update(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return ($this->esDueno($user, $escuelaNivel) || $this->esResponsable($user, $escuelaNivel))
            && $this->esEditable($escuelaNivel);
    }

    /** Datos del inmueble (3.1): son del plantel, compartido entre niveles; solo el dueño. */
    public function updateInmueble(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return $this->esDueno($user, $escuelaNivel) && $this->esEditable($escuelaNivel);
    }

    /** Invitar y revocar responsables de este nivel; un trámite enviado ya no toma ni pierde responsables. */
    public function gestionarResponsables(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return $this->esDueno($user, $escuelaNivel) && $this->esEditable($escuelaNivel);
    }

    private function esEditable(EscuelaNivel $escuelaNivel): bool
    {
        return $this->editable->esEditable((int) $escuelaNivel->escuela_id);
    }

    private function esDueno(User $user, EscuelaNivel $escuelaNivel): bool
    {
        if ($user->solicitante === null) {
            return false;
        }

        return $this->verificar->ejecutar($escuelaNivel->getKey(), $user->solicitante->getKey());
    }

    private function esResponsable(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return $this->accesoResponsable->alNivel($user->getKey(), $escuelaNivel->getKey());
    }
}
