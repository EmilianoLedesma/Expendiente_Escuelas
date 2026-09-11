<?php

namespace App\Application\Documentos\DTO;

/**
 * Entrada de RegistrarDocumento. Plana, readonly, mismo patrón que
 * DatosResponsableLegal — los campos de las 2 tablas de extensión
 * conviven aquí; solo los que corresponden a la clave del documento se
 * usan. fechaEmision aplica a cualquier documento con vigencia
 * (dictamen_uso_suelo, constancia_seguridad_estructural).
 */
final readonly class DatosDocumento
{
    public function __construct(
        public ?string $fechaEmision = null,
        // constancia_seguridad_estructural
        public ?string $peritoNombre = null,
        public ?string $peritoCedulaProfesional = null,
        public ?string $peritoRegistroDro = null,
        public ?string $peritoRegistroAutoridad = null,
        public ?string $peritoRegistroVigencia = null,
        // escritura_inmueble (acreditacion_ocupacion_legal), 4 variantes
        public ?string $tipoAcreditacion = null,
        public ?string $numeroEscritura = null,
        public ?string $notarioNombre = null,
        public ?string $notarioNumero = null,
        public ?string $notarioLocalidad = null,
        public ?string $folioRpp = null,
        public ?string $fechaInscripcionRpp = null,
        public ?string $arrendadorComodante = null,
        public ?string $arrendatarioComodatario = null,
        public ?string $fechaContrato = null,
        public ?string $vigenciaContrato = null,
        public ?string $usoAutorizado = null,
        public ?bool $ratificadoNotario = null,
        public ?string $otroEspecifique = null,
        public ?string $observaciones = null,
    ) {}
}
