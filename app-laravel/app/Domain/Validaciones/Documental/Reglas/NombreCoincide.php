<?php

namespace App\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\NormalizadorNombre;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;

/**
 * Names match when their normalized tokens form the same multiset, in any
 * order (the INE prints surnames first). Any other difference is an alert,
 * never a block (owner decision, ADR-007): an omitted middle name is not
 * impersonation, a reviewer decides.
 */
final readonly class NombreCoincide extends ReglaDeCoincidencia
{
    /** @param list<string> $fuentes */
    public function __construct(string $clave, TipoHecho $tipo, array $fuentes, private NormalizadorNombre $normalizador)
    {
        parent::__construct($clave, $tipo, $fuentes);
    }

    protected function coinciden(string $a, string $b): bool
    {
        $tokensA = $this->normalizador->tokens($a);
        $tokensB = $this->normalizador->tokens($b);
        sort($tokensA);
        sort($tokensB);

        return $tokensA === $tokensB;
    }

    protected function estadoSiDifiere(): EstadoResultado
    {
        return EstadoResultado::Advertencia;
    }

    protected function etiqueta(): string
    {
        return $this->tipo === TipoHecho::NombreFiscal ? 'el nombre o razón social' : 'el nombre';
    }
}
