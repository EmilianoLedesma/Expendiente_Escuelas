<?php

namespace App\Application\Visita\DTO;

use Illuminate\Support\Carbon;

/** Un renglón del registro de visitas realizadas: la visita, su nivel y el estado ACTUAL de ese nivel. */
final readonly class FilaVisitaRealizada
{
    public function __construct(
        public int $visitaId,
        public int $escuelaNivelId,
        public int $escuelaId,
        public string $escuela,
        public string $nivel,
        public int $numero,
        public Carbon $realizadaEl,
        public string $inspector,
        public string $resultado,
        public ?Carbon $subsanarHasta,
        public int $observacionesCount,
        public int $respuestasCount,
        public bool $tieneActa,
        public string $nivelEstado,
        public string $nivelEstadoEtiqueta,
    ) {}
}
