<?php

namespace App\Domain\Validaciones\Regla;

/** One reglas_validacion row, as the capacity engine reads it. */
final readonly class ReglaCapacidad
{
    public function __construct(
        public string $clave,
        public string $tipoRegla,
        public string $tipoCalculo,
        public string $ambito,
        public string $redondeo,
        public string $concepto,
        public ?int $cargoPuestoId,
        public ?float $condicionMin,
        public float $valorNumerico,
        public ?float $condicionMax = null,
    ) {}

    /** The part after "{nivel}.", e.g. "superficie.aulas". */
    public function slug(): string
    {
        return substr($this->clave, strpos($this->clave, '.') + 1);
    }

    public function nivel(): string
    {
        return strtok($this->clave, '.');
    }
}
