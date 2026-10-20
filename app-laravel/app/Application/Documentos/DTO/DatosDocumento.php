<?php

namespace App\Application\Documentos\DTO;

/**
 * Entrada de RegistrarDocumento. Plana, readonly, mismo patrón que
 * DatosResponsableLegal — los campos de las 3 tablas de extensión
 * conviven aquí; solo los que corresponden a la clave del documento se
 * usan. fechaEmision aplica a cualquier documento con vigencia
 * (dictamen_uso_suelo, constancia_seguridad_estructural).
 */
final readonly class DatosDocumento
{
    public function __construct(
        public ?string $fechaEmision = null,
        // dictamen_uso_suelo (dictamenes_uso_suelo)
        public ?string $autoridadExpide = null,
        public ?string $folioDictamen = null,
        public ?string $giroAutorizado = null,
        // Vigencia que declara el documento: fecha_expresa | sin_fecha_expresa; null = no capturada.
        public ?string $tipoVigencia = null,
        public ?string $fechaVencimientoDocumento = null,
        public ?string $condicionantes = null,
        // constancia_seguridad_estructural
        public ?string $peritoNombre = null,
        public ?string $peritoCedulaProfesional = null,
        public ?string $peritoRegistroDro = null,
        public ?string $peritoRegistroAutoridad = null,
        public ?string $peritoRegistroVigencia = null,
        public ?string $vigenciaConstancia = null,
        // Calidad en que firma: dro | corresponsable_seguridad_estructural | otra (con descripción); null = no capturada.
        // La vigencia de la constancia reutiliza tipoVigencia/vigenciaConstancia; peritoRegistroDro guarda el número de registro de cualquier calidad.
        public ?string $calidadProfesional = null,
        public ?string $calidadProfesionalOtra = null,
        public ?string $folioConstancia = null,
        // Fecha de registro o refrendo y vencimiento del registro profesional, tal como las declara el documento.
        public ?string $peritoRegistroFecha = null,
        public ?string $peritoRegistroVencimiento = null,
        // escritura_inmueble (acreditacion_ocupacion_legal), 4 variantes
        public ?string $tipoAcreditacion = null,
        public ?string $numeroEscritura = null,
        public ?string $fechaEscritura = null,
        public ?string $notarioNombre = null,
        public ?string $notarioNumero = null,
        public ?string $notarioLocalidad = null,
        // Ubicación de la notaría desde el catálogo geográfico (ADR-017); notarioLocalidad queda para filas anteriores.
        public ?int $notarioEntidadFederativaId = null,
        public ?int $notarioMunicipioId = null,
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
        public ?string $propietarioNombre = null,
        public ?string $fechaInicioVigencia = null,
        public ?bool $vigenciaIndeterminada = null,
        public ?bool $arrendadorEsPropietario = null,
        public ?string $facultadesReferencia = null,
        public ?string $ratificacionFecha = null,
        public ?string $ratificacionNotarioNombre = null,
        public ?string $ratificacionNotariaNumero = null,
        public ?string $otroReferencia = null,
        public ?string $otroTitular = null,
        public ?string $otroFechaExpedicion = null,
        public ?string $otroAutoridad = null,
        public ?string $otroDerecho = null,
        // ine, constancia_curp: identity of the person who acts (ADR-007)
        public ?string $identidadNombre = null,
        public ?string $identidadCurp = null,
        // constancia_situacion_fiscal
        public ?string $fiscalNombre = null,
        public ?string $fiscalRfc = null,
        // certificado_numero_oficial
        public ?string $domicilioCalle = null,
        public ?string $domicilioNumeroExt = null,
        public ?string $domicilioColonia = null,
        public ?string $domicilioMunicipio = null,
        public ?string $domicilioCodigoPostal = null,
        // recibo_pago_derechos (recibos_pago_derechos, Paso 2.4)
        public ?string $folio = null,
        public ?string $monto = null,
        public ?string $fechaPago = null,
        public ?string $portalReferencia = null,
        // acervo_bibliografico_primaria / _secundaria (relaciones_acervo_bibliografico)
        public ?int $acervoTitulos = null,
    ) {}
}
