<?php

namespace App\Application\Geografia;

use App\Models\EntidadFederativa;
use App\Models\Municipio;

/**
 * Lectura del catálogo geográfico local para los selectores (ADR-006: las
 * pantallas no consultan tablas por su cuenta). Solo id y nombre, ordenados.
 */
class ListarCatalogoGeografico
{
    /** @return list<array{id: int, nombre: string}> */
    public function entidades(): array
    {
        return EntidadFederativa::query()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (EntidadFederativa $entidad): array => ['id' => $entidad->id, 'nombre' => $entidad->nombre])
            ->all();
    }

    /** @return list<array{id: int, nombre: string}> */
    public function municipios(?int $entidadId): array
    {
        if ($entidadId === null) {
            return [];
        }

        return Municipio::query()->where('entidad_federativa_id', $entidadId)->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (Municipio $municipio): array => ['id' => $municipio->id, 'nombre' => $municipio->nombre])
            ->all();
    }
}
