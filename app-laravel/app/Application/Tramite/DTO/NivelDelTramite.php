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
        /** Estado del nivel tras la revisión documental ("Visita programada para el …"); null si aún no la pasó. */
        public readonly ?string $estadoTexto = null,
        /** Clave del estado si el nivel ya pasó la revisión documental (EstadosPosteriores); null si no. */
        public readonly ?string $estadoClave = null,
        /** Quien mira es el dueño (vista completa): puede responder las observaciones de la visita. */
        public readonly bool $puedeResponderVisita = false,
    ) {}
}
