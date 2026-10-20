<?php

namespace App\Application\Documentos\DTO;

use App\Domain\Documentos\Domicilio;

/**
 * Un domicilio declarado por un documento capturado, con su trazabilidad: qué documento, de qué tipo, quién lo
 * emite, en qué jurisdicción, de dónde sale el dato (`procedencia`) y con qué certeza. Los valores del domicilio
 * son los originales; normalizar es cosa del comparador.
 */
final readonly class FuenteDomicilio
{
    public function __construct(
        public int $documentoId,
        public string $tipoDocumento,
        public Domicilio $domicilio,
        public ?string $autoridad,
        public ?string $jurisdiccion,
        public string $procedencia,
        public string $certeza,
    ) {}
}
