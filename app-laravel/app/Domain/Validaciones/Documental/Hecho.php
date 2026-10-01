<?php

namespace App\Domain\Validaciones\Documental;

use InvalidArgumentException;

/**
 * One structured fact the rules can read. The value is kept exactly as
 * captured; normalization happens only when a rule compares, so the audit
 * trail shows what the source actually said.
 */
final readonly class Hecho
{
    public function __construct(
        public TipoHecho $tipo,
        public string $valor,
        public OrigenHecho $origen,
        public ?string $documentoClave,
    ) {
        if (trim($valor) === '') {
            throw new InvalidArgumentException("Hecho {$tipo->value} con valor vacío.");
        }

        if (($origen === OrigenHecho::Documento) !== ($documentoClave !== null)) {
            throw new InvalidArgumentException('Un hecho de documento requiere documentoClave; uno declarado no la admite.');
        }
    }

    public static function declarado(TipoHecho $tipo, string $valor): self
    {
        return new self($tipo, $valor, OrigenHecho::Declarado, null);
    }

    public static function deDocumento(TipoHecho $tipo, string $valor, string $documentoClave): self
    {
        return new self($tipo, $valor, OrigenHecho::Documento, $documentoClave);
    }
}
