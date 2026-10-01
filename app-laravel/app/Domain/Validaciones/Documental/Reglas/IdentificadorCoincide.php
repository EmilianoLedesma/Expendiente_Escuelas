<?php

namespace App\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;

/**
 * Exact identifiers (CURP, RFC): compared after trim/uppercase, with a
 * structural format check (no check digit). A mismatch is NO_CUMPLE. With
 * nothing valid declared (e.g. a persona moral's representative has no
 * declared CURP) the documents are compared among themselves.
 */
final readonly class IdentificadorCoincide extends ReglaDeCoincidencia
{
    public const PATRON_CURP = '/^[A-Z][AEIOUX][A-Z]{2}\d{6}[HMX][A-Z]{2}[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d$/';

    /** 13 chars for personas físicas, 12 for morales. */
    public const PATRON_RFC = '/^[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}$/u';

    /** @param list<string> $fuentes */
    public function __construct(string $clave, TipoHecho $tipo, array $fuentes, private string $patron, private string $nombre)
    {
        parent::__construct($clave, $tipo, $fuentes);
    }

    /** @param list<string> $fuentes */
    public static function curp(array $fuentes): self
    {
        return new self('curp_coincide', TipoHecho::Curp, $fuentes, self::PATRON_CURP, 'la CURP');
    }

    /** @param list<string> $fuentes */
    public static function rfc(array $fuentes): self
    {
        return new self('rfc_coincide', TipoHecho::Rfc, $fuentes, self::PATRON_RFC, 'el RFC');
    }

    protected function coinciden(string $a, string $b): bool
    {
        return $this->normalizar($a) === $this->normalizar($b);
    }

    protected function formatoValido(string $valor): bool
    {
        return preg_match($this->patron, $this->normalizar($valor)) === 1;
    }

    protected function estadoSiDifiere(): EstadoResultado
    {
        return EstadoResultado::NoCumple;
    }

    protected function comparaSinDeclarado(): bool
    {
        return true;
    }

    protected function etiqueta(): string
    {
        return $this->nombre;
    }

    private function normalizar(string $valor): string
    {
        return mb_strtoupper(trim($valor), 'UTF-8');
    }
}
