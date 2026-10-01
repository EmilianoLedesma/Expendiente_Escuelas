<?php

namespace App\Domain\Validaciones\Documental;

use App\Domain\Validaciones\Documental\Reglas\CantidadCoincide;
use App\Domain\Validaciones\Documental\Reglas\DocumentosRequeridosPresentes;
use App\Domain\Validaciones\Documental\Reglas\DomicilioCoincide;
use App\Domain\Validaciones\Documental\Reglas\IdentificadorCoincide;
use App\Domain\Validaciones\Documental\Reglas\InventarioConLaboratorio;
use App\Domain\Validaciones\Documental\Reglas\NombreCoincide;
use App\Domain\Validaciones\Documental\Reglas\ReciboNoReutilizado;

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

    public const FUENTES_ACERVO = ['acervo_bibliografico_primaria', 'acervo_bibliografico_secundaria'];

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

    /**
     * Per escuela_nivel (Paso 2.4). Only the rules whose documents apply to
     * the level are included, so a Primaria never reports an inventory check.
     *
     * @param  list<string>  $clavesRequeridas  DocumentosNivelCompletos::clavesAplicables
     * @return list<ReglaDocumental>
     */
    public static function reglasDeNivel(array $clavesRequeridas): array
    {
        $reglas = [
            new DocumentosRequeridosPresentes(DocumentosRequeridosPresentes::CLAVE_NIVEL),
            new ReciboNoReutilizado,
        ];

        if (array_intersect(self::FUENTES_ACERVO, $clavesRequeridas) !== []) {
            $reglas[] = new CantidadCoincide('acervo_coincide', TipoHecho::TitulosAcervo, self::FUENTES_ACERVO);
        }

        if (in_array(InventarioConLaboratorio::DOCUMENTO, $clavesRequeridas, true)) {
            $reglas[] = new InventarioConLaboratorio;
        }

        return $reglas;
    }
}
