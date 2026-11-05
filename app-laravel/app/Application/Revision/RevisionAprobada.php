<?php

namespace App\Application\Revision;

/** Una decisión dejó todos los niveles de la escuela aprobados: la revisión terminó sin observaciones. */
final class RevisionAprobada
{
    public function __construct(public readonly int $escuelaId) {}
}
