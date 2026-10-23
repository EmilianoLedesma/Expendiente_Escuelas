<?php

namespace App\Application\Revision;

/** Una decisión dejó la escuela con la ronda de revisión cerrada (ningún nivel en revisión, al menos uno devuelto). */
final class RondaCerrada
{
    public function __construct(public readonly int $escuelaId) {}
}
