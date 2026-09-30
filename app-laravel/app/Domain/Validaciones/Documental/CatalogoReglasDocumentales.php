<?php

namespace App\Domain\Validaciones\Documental;

use App\Domain\Validaciones\Documental\Reglas\DocumentosRequeridosPresentes;
use App\Domain\Validaciones\Documental\Reglas\DomicilioCoincide;
use App\Domain\Validaciones\Documental\Reglas\IdentificadorCoincide;
use App\Domain\Validaciones\Documental\Reglas\NombreCoincide;

/**
 * The documental rule set and which documents carry each fact
 * (tipos_documentos.clave). Adding a rule means adding it here; existing
 * rules don't change.
 */
final class CatalogoReglasDocumentales
{
    public const FUENTES_IDENTIDAD = ['ine', 'constancia_curp'];

    public const FUENTE_FISCAL = 'constancia_situacion_fiscal';

    public const FUENTE_DOMICILIO = 'certificado_numero_oficial';

    /** @return list<ReglaDocumental> */
    public static function reglas(): array
    {
        $nombres = new NormalizadorNombre;

        return [
            new DocumentosRequeridosPresentes,
            new NombreCoincide('nombre_identidad_coincide', TipoHecho::NombreIdentidad, self::FUENTES_IDENTIDAD, $nombres),
            IdentificadorCoincide::curp(self::FUENTES_IDENTIDAD),
            new NombreCoincide('nombre_fiscal_coincide', TipoHecho::NombreFiscal, [self::FUENTE_FISCAL], $nombres),
            IdentificadorCoincide::rfc([self::FUENTE_FISCAL]),
            new DomicilioCoincide(self::FUENTE_DOMICILIO, new NormalizadorDomicilio),
        ];
    }
}
