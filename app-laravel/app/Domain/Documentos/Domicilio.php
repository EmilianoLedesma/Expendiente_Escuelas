<?php

namespace App\Domain\Documentos;

/**
 * Domicilio tal como lo declara una fuente (el plantel o un documento): valores originales, sin normalizar.
 * `null` o cadena en blanco = la fuente no lo declara; «S/N» = la fuente declara que no tiene número.
 * `municipioId` es la clave del catálogo geográfico local cuando la fuente la tiene; el texto se conserva igual.
 */
final readonly class Domicilio
{
    public function __construct(
        public ?string $calle = null,
        public ?string $numeroExt = null,
        public ?string $numeroInt = null,
        public ?string $colonia = null,
        public ?string $municipio = null,
        public ?string $codigoPostal = null,
        public ?int $municipioId = null,
    ) {}
}
