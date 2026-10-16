<?php

namespace App\Application\Revision;

use DomainException;

/** El revisor ya tiene el tope de trámites en revisión; el supervisor puede confirmar la sobrecarga. */
final class LimiteDeRevisorAlcanzado extends DomainException
{
    public function __construct(public readonly int $revisorId, public readonly int $limite)
    {
        parent::__construct("El revisor ya tiene {$limite} trámites asignados.");
    }
}
