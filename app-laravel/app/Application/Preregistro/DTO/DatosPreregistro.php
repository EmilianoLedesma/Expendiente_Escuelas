<?php

namespace App\Application\Preregistro\DTO;

/**
 * Entrada de IniciarTramiteNuevo. Plana, readonly — lo que permite que un
 * futuro controlador de API construya el mismo caso de uso sin pasar por
 * Livewire (ver ADR-001).
 *
 * `bifurcacion` distingue las dos ramas de PRD §5 Paso 1: 'nuevo' crea un
 * `plantel` desde los campos de dirección; 'existente' reutiliza un
 * `plantel_id` ya registrado (selector simple — la precarga inteligente es
 * Etapa 3, fuera de alcance).
 */
final readonly class DatosPreregistro
{
    public function __construct(
        public string $bifurcacion,
        public ?int $plantelId = null,
        public ?string $calle = null,
        public ?string $numeroExt = null,
        public ?string $numeroInt = null,
        public ?string $colonia = null,
        public ?string $localidad = null,
        public ?string $municipio = null,
        public ?string $codigoPostal = null,
        public ?string $telefono = null,
        public ?string $correoElectronico = null,
    ) {}
}
