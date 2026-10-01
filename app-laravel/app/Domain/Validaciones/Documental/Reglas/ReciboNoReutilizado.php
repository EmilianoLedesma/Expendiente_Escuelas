<?php

namespace App\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\ReglaDocumental;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ResultadoRegla;

/**
 * One payment covers one escuela_nivel: a recibo folio already registered
 * by another level (of this or any other trámite) blocks. Folios compare
 * ignoring case and surrounding spaces.
 */
final readonly class ReciboNoReutilizado implements ReglaDocumental
{
    public const CLAVE = 'recibo_no_reutilizado';

    public const DOCUMENTO = 'recibo_pago_derechos';

    public function evaluar(ContextoValidacion $contexto): ResultadoRegla
    {
        if (! $contexto->presente(self::DOCUMENTO)) {
            return new ResultadoRegla(self::CLAVE, EstadoResultado::NoEvaluable, 'No se ha cargado el recibo de pago de derechos.');
        }

        $folio = $contexto->deDocumento(TipoHecho::FolioRecibo, self::DOCUMENTO)?->valor;

        if ($folio === null) {
            return new ResultadoRegla(self::CLAVE, EstadoResultado::NoCumple, 'Falta capturar el folio del recibo; vuelve a subirlo con sus datos.', [], [self::DOCUMENTO]);
        }

        $ajenos = array_map($this->normalizar(...), $contexto->foliosAjenos);

        return in_array($this->normalizar($folio), $ajenos, true)
            ? new ResultadoRegla(self::CLAVE, EstadoResultado::NoCumple, 'El folio del recibo ya está registrado en otro nivel o trámite; cada nivel requiere su propio pago.', ['folio' => $folio], [self::DOCUMENTO])
            : new ResultadoRegla(self::CLAVE, EstadoResultado::Cumple, 'El folio del recibo no está registrado en otro nivel o trámite.', ['folio' => $folio]);
    }

    private function normalizar(string $folio): string
    {
        return mb_strtoupper(trim($folio));
    }
}
