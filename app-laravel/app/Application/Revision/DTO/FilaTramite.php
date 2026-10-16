<?php

namespace App\Application\Revision\DTO;

/** Un nivel en revisión, tal como lo ven el tablero del supervisor y la cola del revisor. */
final readonly class FilaTramite
{
    public function __construct(
        public int $escuelaNivelId,
        public int $escuelaId,
        public string $plantel,
        public string $nivel,
        public string $prioridad,
        public int $diasEspera,
        public ?int $revisorId,
        public ?string $revisorNombre,
        public int $revisadas,
        public int $total,
    ) {}
}
