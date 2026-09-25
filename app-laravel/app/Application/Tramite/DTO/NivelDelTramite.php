<?php

namespace App\Application\Tramite\DTO;

final class NivelDelTramite
{
    /** @param array<int, SeccionTramite> $secciones */
    public function __construct(
        public readonly int $escuelaNivelId,
        public readonly string $clave,
        public readonly string $nombre,
        public readonly array $secciones,
    ) {}
}
