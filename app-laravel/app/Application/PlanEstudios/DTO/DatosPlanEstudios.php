<?php

namespace App\Application\PlanEstudios\DTO;

/** Turno and tipo de alumnado are not here: they are captured in Paso 2.4 (WS-5b). */
final readonly class DatosPlanEstudios
{
    public function __construct(
        public string $modalidad,
        public ?string $planEstudiosReferencia = null,
        public ?string $plataformaEducativaTipo = null,
    ) {}
}
