<?php

namespace App\Domain\Validaciones\Documental;

/**
 * Closed catalog of fact types the rules compare.
 *
 * "Identidad" is the person who acts and signs: the titular (fisica), the
 * gestor (fisica_con_gestor) or the legal representative (moral).
 * "Fiscal" is the taxpayer: the titular, or the persona moral itself.
 * Which declared value feeds each type is decided by the context builder
 * (App\Application\Validaciones\ConstruirContextoValidacion), so the rules
 * never branch on tipo_persona.
 */
enum TipoHecho: string
{
    case NombreIdentidad = 'nombre_identidad';
    case Curp = 'curp';
    case NombreFiscal = 'nombre_fiscal';
    case Rfc = 'rfc';
    case DomicilioCalle = 'domicilio_calle';
    case DomicilioNumeroExt = 'domicilio_numero_ext';
    case DomicilioColonia = 'domicilio_colonia';
    case DomicilioMunicipio = 'domicilio_municipio';
    case DomicilioCodigoPostal = 'domicilio_codigo_postal';
    // Per-level facts (Paso 2.4, WS-5b).
    case FolioRecibo = 'folio_recibo';
    case TitulosAcervo = 'titulos_acervo';
    case LaboratoriosDeclarados = 'laboratorios_declarados';

    /** @return list<self> */
    public static function domicilio(): array
    {
        return [self::DomicilioCalle, self::DomicilioNumeroExt, self::DomicilioColonia, self::DomicilioMunicipio, self::DomicilioCodigoPostal];
    }
}
