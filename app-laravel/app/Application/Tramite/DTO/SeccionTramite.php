<?php

namespace App\Application\Tramite\DTO;

/** Una fila del hub. estado: completado|en_curso|pendiente|no_disponible|no_aplica; accion: comenzar|continuar|revisar|null. */
final class SeccionTramite
{
    public function __construct(
        public readonly string $clave,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $estado,
        public readonly ?string $accion,
        public readonly ?string $href,
        public readonly ?string $motivoBloqueo,
        public readonly ?int $paso,
        public readonly ?int $totalPasos,
    ) {}
}
