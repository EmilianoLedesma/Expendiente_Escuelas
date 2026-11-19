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
        /** Clave del estado del nivel (el estado filtrado). */
        public string $estado,
        /** Hay una respuesta del solicitante más reciente que el último acta o aceptación de la visita. */
        public bool $respuestaPendiente = false,
    ) {}
}
