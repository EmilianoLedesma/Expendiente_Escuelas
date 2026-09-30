<?php

namespace App\Domain\Validaciones\Resultado;

final readonly class ResultadoRegla
{
    /** @param array<string, mixed> $detalles */
    public function __construct(
        public string $clave,
        public EstadoResultado $estado,
        public string $mensaje,
        public array $detalles = [],
    ) {}
}
