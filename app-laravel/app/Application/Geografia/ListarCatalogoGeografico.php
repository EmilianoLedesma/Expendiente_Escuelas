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
    /** Clave de Querétaro en el catálogo de origen (ADR-017): el plantel debe estar en esta entidad (ADR-008). */
    public const CLAVE_QUERETARO = 22;

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

    /** Municipios de Querétaro para el selector del plantel; vacío si el catálogo no está importado. @return list<array{id: int, nombre: string}> */
    public function municipiosDeQueretaro(): array
    {
        return $this->municipios($this->idDeQueretaro());
    }

    /** id local de la entidad Querétaro, o null si el catálogo no está importado. */
    public function idDeQueretaro(): ?int
    {
        $id = EntidadFederativa::where('clave', self::CLAVE_QUERETARO)->value('id');

        return $id === null ? null : (int) $id;
    }
}
