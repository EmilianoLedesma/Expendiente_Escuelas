<?php

namespace App\Application\Tramite;

use App\Application\Tramite\DTO\ResumenTramiteDTO;
use App\Models\Escuela;

/** Alimenta "Mis trámites": solo las escuelas del solicitante, más reciente primero. */
final class ListarTramitesDelSolicitante
{
    public function __construct(private readonly ResumenTramite $resumenTramite) {}

    /** @return array<int, ResumenTramiteDTO> */
    public function ejecutar(int $solicitanteId): array
    {
        // ponytail: un resumen (varias consultas) por escuela; agregar/paginar si un solicitante llega a decenas de trámites.
        return Escuela::where('solicitante_id', $solicitanteId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn (int $id) => $this->resumenTramite->paraEscuela($id))
            ->all();
    }
}
