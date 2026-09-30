<?php

namespace App\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\NormalizadorNombre;
use App\Domain\Validaciones\Documental\ReglaDocumental;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ResultadoRegla;

/**
 * Declared holder name vs. the name on the INE. Token order is ignored
 * (the INE prints surnames first; personas_fisicas.nombre is one free-text
 * field). Any token difference is a WARNING, never NO_CUMPLE: an omitted
 * middle name is not impersonation, and a human reviewer decides
 * (PENDIENTE-motor-validacion-hechos P2).
 */
final readonly class NombreTitularCoincide implements ReglaDocumental
{
    public const CLAVE = 'nombre_titular_coincide';

    public function __construct(private NormalizadorNombre $normalizador) {}

    public function evaluar(ContextoValidacion $contexto): ResultadoRegla
    {
        if ($contexto->tipoPersona !== 'fisica') {
            return $this->resultado(EstadoResultado::NoEvaluable, 'No se ha decidido de quién es la INE para este tipo de persona (docs/decisions/PENDIENTE-motor-validacion-hechos.md, P8).');
        }

        $declarado = $contexto->declarado(TipoHecho::NombreTitular);
        $enIne = $contexto->deDocumento(TipoHecho::NombreTitular, 'ine');

        if ($declarado === null || $enIne === null) {
            return $this->resultado(EstadoResultado::NoEvaluable, $declarado === null
                ? 'No hay nombre declarado del titular.'
                : 'No se ha capturado el nombre que aparece en la INE.');
        }

        $tokensDeclarado = $this->normalizador->tokens($declarado->valor);
        $tokensIne = $this->normalizador->tokens($enIne->valor);

        if ($this->mismoMulticonjunto($tokensDeclarado, $tokensIne)) {
            return $this->resultado(EstadoResultado::Cumple, 'El nombre declarado coincide con la INE.', [
                'orden_distinto' => $tokensDeclarado !== $tokensIne,
            ]);
        }

        return $this->resultado(EstadoResultado::Advertencia, 'El nombre declarado no coincide con el de la INE; se revisará manualmente.', [
            'solo_declarado' => $this->diferencia($tokensDeclarado, $tokensIne),
            'solo_documento' => $this->diferencia($tokensIne, $tokensDeclarado),
        ]);
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function mismoMulticonjunto(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * Multiset difference: tokens of $a not matched one-to-one in $b.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<string>
     */
    private function diferencia(array $a, array $b): array
    {
        $restantes = $b;
        $sobrantes = [];

        foreach ($a as $token) {
            $indice = array_search($token, $restantes, true);

            if ($indice === false) {
                $sobrantes[] = $token;
            } else {
                unset($restantes[$indice]);
            }
        }

        return $sobrantes;
    }

    /** @param array<string, mixed> $detalles */
    private function resultado(EstadoResultado $estado, string $mensaje, array $detalles = []): ResultadoRegla
    {
        return new ResultadoRegla(self::CLAVE, $estado, $mensaje, $detalles);
    }
}
