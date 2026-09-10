<?php

namespace App\Application\ResponsableLegal\DTO;

/**
 * Entrada de RegistrarResponsableLegal. Plana, readonly, mismo patrón que
 * DatosPreregistro — un futuro controlador de API construye el mismo
 * caso de uso sin pasar por Livewire (ADR-001). Los campos de las tres
 * variantes de tipo_persona conviven aquí; solo los que corresponden a
 * `tipoPersona` se usan.
 */
final readonly class DatosResponsableLegal
{
    public function __construct(
        public string $tipoPersona,
        public ?string $domicilioNotificaciones = null,
        public ?string $personaAutorizadaRecoger = null,
        // terna de nombres propuestos (COMPENDIO: mismo dato referenciado en Paso 3/Anexo 2)
        public ?string $nombrePropuesto1 = null,
        public ?string $nombrePropuesto2 = null,
        public ?string $nombrePropuesto3 = null,
        // persona física
        public ?string $nombre = null,
        public ?string $fechaNacimiento = null,
        public ?string $rfc = null,
        public ?string $curp = null,
        // persona moral
        public ?string $razonSocial = null,
        public ?string $numeroEscrituraConstitutiva = null,
        public ?string $fechaEscrituraConstitutiva = null,
        public ?string $notarioNombre = null,
        public ?string $notarioNumero = null,
        public ?string $notarioCiudad = null,
        public ?string $folioRegistroPublico = null,
        public ?string $fechaInscripcionRpp = null,
        public ?string $nombreRepresentanteLegal = null,
        // gestor (solo si tipoPersona === 'fisica_con_gestor')
        public ?string $gestorNombre = null,
        public ?string $gestorNumeroPoder = null,
        public ?string $gestorNotarioNombre = null,
        public ?string $gestorNotarioNumero = null,
        public ?string $gestorFechaPoder = null,
    ) {}
}
