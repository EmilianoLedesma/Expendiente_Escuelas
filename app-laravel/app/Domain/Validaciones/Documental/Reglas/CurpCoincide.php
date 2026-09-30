<?php

namespace App\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\ReglaDocumental;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ResultadoRegla;

/**
 * Declared CURP vs. the CURP on the INE. A CURP is an exact identifier with
 * no legitimate spelling variants, so a mismatch is NO_CUMPLE — which is
 * still not a block by itself (see EstadoResultado). Only the structural
 * format is checked, not the RENAPO check digit.
 */
final readonly class CurpCoincide implements ReglaDocumental
{
    public const CLAVE = 'curp_coincide';

    private const FORMATO = '/^[A-Z][AEIOUX][A-Z]{2}\d{6}[HMX][A-Z]{2}[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d$/';

    public function evaluar(ContextoValidacion $contexto): ResultadoRegla
    {
        if ($contexto->tipoPersona !== 'fisica') {
            return $this->resultado(EstadoResultado::NoEvaluable, 'No se ha decidido de quién es la INE para este tipo de persona, y personas_morales no guarda CURP (docs/decisions/PENDIENTE-motor-validacion-hechos.md, P8).');
        }

        $declarada = $contexto->declarado(TipoHecho::Curp);
        $enIne = $contexto->deDocumento(TipoHecho::Curp, 'ine');

        if ($declarada === null || $enIne === null) {
            return $this->resultado(EstadoResultado::NoEvaluable, $declarada === null
                ? 'No hay CURP declarada del titular.'
                : 'No se ha capturado la CURP que aparece en la INE.');
        }

        $curpDeclarada = strtoupper(trim($declarada->valor));
        $curpIne = strtoupper(trim($enIne->valor));

        if (preg_match(self::FORMATO, $curpDeclarada) !== 1 || preg_match(self::FORMATO, $curpIne) !== 1) {
            return $this->resultado(EstadoResultado::NoEvaluable, 'Alguna de las CURP no tiene un formato válido.');
        }

        return $curpDeclarada === $curpIne
            ? $this->resultado(EstadoResultado::Cumple, 'La CURP declarada coincide con la INE.')
            : $this->resultado(EstadoResultado::NoCumple, 'La CURP declarada no coincide con la de la INE.');
    }

    private function resultado(EstadoResultado $estado, string $mensaje): ResultadoRegla
    {
        return new ResultadoRegla(self::CLAVE, $estado, $mensaje);
    }
}
