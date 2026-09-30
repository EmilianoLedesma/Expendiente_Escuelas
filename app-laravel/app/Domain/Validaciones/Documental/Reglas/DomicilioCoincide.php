<?php

namespace App\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\NormalizadorDomicilio;
use App\Domain\Validaciones\Documental\ReglaDocumental;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ResultadoRegla;

/**
 * COMPENDIO §Paso 2: every document must match the official address "tal
 * cual el certificado de número oficial". Declared plantel address vs. the
 * certificate, part by part. A different código postal is NO_CUMPLE (exact
 * identifier); any other part that differs is an alert. Parts the plantel
 * did not declare are not compared.
 */
final readonly class DomicilioCoincide implements ReglaDocumental
{
    public const CLAVE = 'domicilio_coincide';

    public function __construct(private string $fuente, private NormalizadorDomicilio $normalizador) {}

    public function evaluar(ContextoValidacion $contexto): ResultadoRegla
    {
        if (! $contexto->presente($this->fuente)) {
            return new ResultadoRegla(self::CLAVE, EstadoResultado::NoEvaluable, 'No se ha cargado el certificado de número oficial.', ['partes' => []]);
        }

        $sinDatos = true;
        foreach (TipoHecho::domicilio() as $tipo) {
            if ($contexto->deDocumento($tipo, $this->fuente) !== null) {
                $sinDatos = false;
            }
        }

        if ($sinDatos) {
            return new ResultadoRegla(self::CLAVE, EstadoResultado::NoCumple, 'Falta capturar el domicilio del certificado de número oficial; vuelve a subirlo con sus datos.', ['partes' => []], [$this->fuente]);
        }

        $partes = [];
        foreach (TipoHecho::domicilio() as $tipo) {
            $declarado = $contexto->declarado($tipo)?->valor;

            if ($declarado === null) {
                continue;
            }

            $documento = $contexto->deDocumento($tipo, $this->fuente)?->valor;
            $partes[$tipo->value] = [
                'declarado' => $declarado,
                'documento' => $documento,
                'estado' => $documento !== null && $this->coinciden($tipo, $declarado, $documento) ? 'coincide' : 'difiere',
            ];
        }

        $difieren = array_keys(array_filter($partes, fn (array $p) => $p['estado'] === 'difiere'));

        if (in_array(TipoHecho::DomicilioCodigoPostal->value, $difieren, true)) {
            return new ResultadoRegla(self::CLAVE, EstadoResultado::NoCumple, 'El código postal del plantel no coincide con el del certificado de número oficial.', ['partes' => $partes], [$this->fuente]);
        }

        if ($difieren !== []) {
            return new ResultadoRegla(self::CLAVE, EstadoResultado::Advertencia, 'El domicilio del plantel no coincide con el del certificado de número oficial.', ['partes' => $partes], [$this->fuente]);
        }

        return new ResultadoRegla(self::CLAVE, EstadoResultado::Cumple, 'El domicilio del plantel coincide con el certificado de número oficial.', ['partes' => $partes]);
    }

    private function coinciden(TipoHecho $tipo, string $a, string $b): bool
    {
        if ($tipo === TipoHecho::DomicilioCodigoPostal) {
            return preg_replace('/\D/', '', $a) === preg_replace('/\D/', '', $b);
        }

        return $this->normalizador->normalizar($a) === $this->normalizador->normalizar($b);
    }
}
