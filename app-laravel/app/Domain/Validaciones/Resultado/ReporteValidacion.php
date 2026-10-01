<?php

namespace App\Domain\Validaciones\Resultado;

final readonly class ReporteValidacion
{
    /** @param list<ResultadoRegla> $resultados */
    public function __construct(public array $resultados) {}

    public function ejecutadas(): int
    {
        return count($this->resultados);
    }

    public function contar(EstadoResultado $estado): int
    {
        return count(array_filter($this->resultados, fn (ResultadoRegla $r) => $r->estado === $estado));
    }

    public function tieneNoCumplimientos(): bool
    {
        return $this->contar(EstadoResultado::NoCumple) > 0;
    }

    public function resultado(string $clave): ?ResultadoRegla
    {
        foreach ($this->resultados as $resultado) {
            if ($resultado->clave === $clave) {
                return $resultado;
            }
        }

        return null;
    }
}
