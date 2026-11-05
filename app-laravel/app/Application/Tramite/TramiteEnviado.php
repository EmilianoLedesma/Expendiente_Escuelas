<?php

namespace App\Application\Tramite;

/** El solicitante envió el trámite a revisión (EnviarTramite, ya confirmado en BD). */
final class TramiteEnviado
{
    public function __construct(public readonly int $escuelaId) {}
}
