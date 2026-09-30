<?php

namespace App\Application\PlanEstudios\DTO;

final readonly class DatosPlanEstudios
{
    public function __construct(
        public string $modalidad,
        public string $turno,
        public string $tipoAlumnado,
        public ?string $planEstudiosReferencia = null,
        public ?string $plataformaEducativaTipo = null,
    ) {}
}
