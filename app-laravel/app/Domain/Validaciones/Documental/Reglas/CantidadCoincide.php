<?php

namespace App\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;

/**
 * A whole-number count (e.g. titles in the acervo) declared in the wizard
 * against the one written in a document. Any difference is an alert: the
 * count may legitimately have changed since capture, a reviewer decides.
 */
final readonly class CantidadCoincide extends ReglaDeCoincidencia
{
    protected function formatoValido(string $valor): bool
    {
        return ctype_digit(trim($valor));
    }

    protected function coinciden(string $a, string $b): bool
    {
        return (int) trim($a) === (int) trim($b);
    }

    protected function estadoSiDifiere(): EstadoResultado
    {
        return EstadoResultado::Advertencia;
    }

    protected function etiqueta(): string
    {
        return $this->tipo === TipoHecho::TitulosAcervo ? 'el número de títulos del acervo' : 'la cantidad';
    }
}
