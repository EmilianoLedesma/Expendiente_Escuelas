<?php

namespace App\Application\Preregistro\DTO;

final readonly class ResultadoPreregistro
{
    public function __construct(
        public int $escuelaId,
        public int $plantelId,
    ) {
    }
}
