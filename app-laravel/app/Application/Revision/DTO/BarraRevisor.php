<?php

namespace App\Application\Revision\DTO;

/** Carga de un revisor activo: niveles en revisión asignados contra su tope, y avance de revisión. */
final readonly class BarraRevisor
{
    public function __construct(
        public int $revisorId,
        public string $nombre,
        public int $asignados,
        public int $limite,
        public int $revisadas,
        public int $total,
    ) {}
}
