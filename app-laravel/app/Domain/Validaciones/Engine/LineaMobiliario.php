<?php

namespace App\Domain\Validaciones\Engine;

/** One mobiliario_conceptos row with what the level declared for it (null = not declared). */
final readonly class LineaMobiliario
{
    public function __construct(
        public string $concepto,
        public ?string $salaClave,
        public string $tipoRatio,
        public float $valorRatio,
        public ?int $declarada,
    ) {}
}
