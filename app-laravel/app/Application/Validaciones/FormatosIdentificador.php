<?php

namespace App\Application\Validaciones;

use App\Domain\Validaciones\Documental\Reglas\IdentificadorCoincide;

/**
 * The same CURP/RFC formats the engine checks, exposed to input forms so
 * capture and validation can't drift apart (Livewire doesn't import Domain,
 * ADR-001).
 */
final class FormatosIdentificador
{
    public const CURP = IdentificadorCoincide::PATRON_CURP;

    public const RFC = IdentificadorCoincide::PATRON_RFC;

    public const CODIGO_POSTAL = '/^\d{5}$/';
}
