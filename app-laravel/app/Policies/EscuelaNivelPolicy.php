<?php

namespace App\Policies;

use App\Application\Escuelas\VerificarAccesoResponsable;
use App\Application\Escuelas\VerificarPropietarioEscuelaNivel;
use App\Application\Revision\AccesoSedeq;
use App\Application\Revision\PasosRevision;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\User;

/**
 * `view` (lectura pura) y `update` (toda página del nivel que escribe)
 * admiten al dueño de la escuela padre O al responsable asignado a ESTE
 * nivel (ADR-015); `view` admite además a SEDEQ (supervisor o el revisor de
 * ESTE nivel, AccesoSedeq). `updateInmueble`, `editarCompartido` y
 * `gestionarResponsables` son solo del dueño. Ninguna ruta que escribe puede apoyarse en `view`: abrirlo
 * a SEDEQ nunca abre escrituras. La propiedad se delega en VerificarPropietarioEscuelaNivel y
 * la asignación en VerificarAccesoResponsable, sin reimplementar comparaciones.
 * Las habilidades que escriben exigen además que el trámite de la
 * escuela padre siga editable (WS-7a, TramiteEditable): tras el envío, 403.
 */
class EscuelaNivelPolicy
{
    public function __construct(
        private readonly VerificarPropietarioEscuelaNivel $verificar,
        private readonly VerificarAccesoResponsable $accesoResponsable,
        private readonly TramiteEditable $editable,
        private readonly AccesoSedeq $accesoSedeq,
    ) {}

    public function view(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return $this->esDueno($user, $escuelaNivel)
            || $this->esResponsable($user, $escuelaNivel)
            || $this->accesoSedeq->puedeVerNivel($user, $escuelaNivel->getKey());
    }

    /** Descargar el Anexo 2 Instalaciones (solo lectura, D3/D4): dueño o responsable de ESTE nivel, aun con el trámite enviado. */
    public function descargarAnexo(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return $this->esDueno($user, $escuelaNivel) || $this->esResponsable($user, $escuelaNivel);
    }

    public function update(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return ($this->esDueno($user, $escuelaNivel) || $this->esResponsable($user, $escuelaNivel))
            && $this->esEditable($escuelaNivel);
    }

    /**
     * Escribir un paso del nivel. Todo en captura: como `update`. Tras una devolución, el dueño corrige
     * cualquier paso reabierto; el responsable solo los suyos (no los generales ni de ámbito plantel,
     * inmueble e infraestructura). Los pasos del nivel se reabren por nivel; los generales, para toda la escuela.
     */
    public function editarPaso(User $user, EscuelaNivel $escuelaNivel, string ...$pasos): bool
    {
        $dueno = $this->esDueno($user, $escuelaNivel);
        if (! $dueno && ! $this->esResponsable($user, $escuelaNivel)) {
            return false;
        }
        $escuelaId = (int) $escuelaNivel->escuela_id;
        if ($this->editable->esEditable($escuelaId)) {
            return true;
        }

        return collect($pasos)->contains(fn (string $paso) => ($dueno || ! in_array($paso, PasosRevision::SOLO_DUENO, true))
            && $this->editable->esEditable($escuelaId, $paso, $escuelaNivel->getKey()));
    }

    /** Datos del inmueble (3.1): son del plantel, compartido entre niveles; solo el dueño, en captura o con `inmueble` reabierto en este nivel. */
    public function updateInmueble(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return $this->esDueno($user, $escuelaNivel) && $this->esEditablePaso($escuelaNivel, 'inmueble');
    }

    /**
     * Corregir o quitar espacios y sanitarios ya capturados del plantel (WS-7b-2): solo el dueño.
     * En captura, o con `infraestructura` reabierto en este nivel tras una devolución.
     */
    public function editarCompartido(User $user, EscuelaNivel $escuelaNivel): bool
    {
        return $this->esDueno($user, $escuelaNivel) && $this->esEditablePaso($escuelaNivel, 'infraestructura');
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

    private function esEditablePaso(EscuelaNivel $escuelaNivel, string $paso): bool
    {
        return $this->editable->esEditable((int) $escuelaNivel->escuela_id, $paso, $escuelaNivel->getKey());
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
