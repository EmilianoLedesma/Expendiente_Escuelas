<?php

namespace App\Application\Geografia;

/**
 * De dónde sale el catálogo geográfico (hoy, la API api_info_mexico; ADR-017).
 * Solo lo usa la importación: la aplicación nunca consulta la fuente en línea.
 */
interface FuenteCatalogoGeografico
{
    /**
     * Incluye las claves no geográficas del origen (0, 88, 99); filtrarlas es del caso de uso.
     *
     * @return list<array{clave: int, nombre: string, abreviatura: ?string}>
     */
    public function entidades(): array;

    /** @return list<array{clave: int, nombre: string}> */
    public function municipios(int $claveEntidad): array;
}
