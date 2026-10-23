<?php

namespace App\Application\Tramite\DTO;

/** Una fila del hub. estado: completado|en_curso|pendiente|con_observaciones|no_disponible|no_aplica; accion: comenzar|continuar|revisar|corregir|null. */
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
        /** Motivo de rechazo del revisor; solo en una sección reabierta con la ronda cerrada (estado con_observaciones). */
        public readonly ?string $observacion = null,
        /** El solicitante puede corregirla por la devolución (sección reabierta). Distinto de «revisar»: esto es una orden de corregir. */
        public readonly bool $editable = false,
    ) {}
}
