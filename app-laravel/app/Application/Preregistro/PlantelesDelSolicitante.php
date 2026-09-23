<?php

namespace App\Application\Preregistro;

use App\Models\Plantel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Única definición de "planteles de este solicitante": los que ya tienen al
 * menos una escuela suya. La usan el selector (ListarPlantelesDisponibles) y
 * la compuerta de propiedad (IniciarTramiteNuevo) — no duplicar el whereHas.
 */
final class PlantelesDelSolicitante
{
    /** @return Builder<Plantel> */
    public static function query(int $solicitanteId): Builder
    {
        return Plantel::query()
            ->whereHas('escuelas', fn ($q) => $q->where('solicitante_id', $solicitanteId));
    }
}
