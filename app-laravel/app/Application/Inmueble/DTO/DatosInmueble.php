<?php

namespace App\Application\Inmueble\DTO;

/**
 * Entrada de RegistrarDatosInmueble. Todo lo que contiene es del plantel
 * (Anexo 2, "Datos del inmueble"): editable la primera vez que el plantel se
 * registra, de solo lectura al reutilizarlo.
 *
 * No incluye `modalidad`: COMPENDIO la circunscribe a Media Superior/Superior
 * y este MVP cubre Educación Básica.
 */
final readonly class DatosInmueble
{
    /**
     * @param  list<array{nombre: string, tipo: string, esPublico: bool|null, distanciaValor: float|null, distanciaUnidad: string|null}>  $serviciosCercanos
     * @param  list<array{nivelEducativoId: int|null, otroNivelTexto: string|null, numeroAlumnos: int}>  $estudiosActuales
     */
    public function __construct(
        public float $metrosTotales,
        public ?float $metrosConstruidos = null,
        public ?string $colindanciaNorte = null,
        public ?string $colindanciaSur = null,
        public ?string $colindanciaEste = null,
        public ?string $colindanciaOeste = null,
        public ?float $latitud = null,
        public ?float $longitud = null,
        public ?float $areaCivicaM2 = null,
        public ?bool $tieneAstaBandera = null,
        public array $serviciosCercanos = [],
        public array $estudiosActuales = [],
    ) {}
}
