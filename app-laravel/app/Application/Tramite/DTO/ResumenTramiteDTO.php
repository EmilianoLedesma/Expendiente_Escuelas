<?php

namespace App\Application\Tramite\DTO;

final class ResumenTramiteDTO
{
    /**
     * @param  array<string, string|null>  $plantel  etiqueta => valor (mismas etiquetas que Paso 1)
     * @param  array<int, SeccionTramite>  $generales
     * @param  array<int, NivelDelTramite>  $niveles
     */
    public function __construct(
        public readonly int $escuelaId,
        public readonly string $domicilio,
        public readonly array $plantel,
        public readonly array $generales,
        public readonly array $niveles,
        public readonly bool $completo,
    ) {}
}
