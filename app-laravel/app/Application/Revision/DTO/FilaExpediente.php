<?php

namespace App\Application\Revision\DTO;

use Illuminate\Support\Carbon;

/** Un nivel en el Expediente del supervisor. */
final readonly class FilaExpediente
{
    public function __construct(
        public int $escuelaNivelId,
        public int $escuelaId,
        public string $escuela,
        public string $nivel,
        /** Cuándo entró el nivel al estado filtrado (último cambio a ese estado); null si no hay historial. */
        public ?Carbon $desde,
        public ?string $revisorNombre,
    ) {}
}
