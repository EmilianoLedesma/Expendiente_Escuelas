<?php

namespace App\Application\Preregistro;

use App\Models\Plantel;
use Illuminate\Support\Collection;

/**
 * Alimenta el selector simple de "plantel ya existente" (PRD §5 Paso 1,
 * bifurcación). La precarga inteligente es Etapa 3 — esto solo lista.
 */
final class ListarPlantelesDisponibles
{
    /** @return Collection<int, array{id: int, etiqueta: non-falsy-string}> */
    public function ejecutar(): Collection
    {
        return Plantel::query()
            ->orderBy('calle')
            ->get(['id', 'calle', 'municipio'])
            ->map(fn (Plantel $p) => ['id' => $p->id, 'etiqueta' => "{$p->calle}, {$p->municipio}"]);
    }
}
