<?php

namespace App\Application\Visita;

/** El supervisor registró el acta (resultado) de una visita de inspección. */
final class ActaDeVisitaRegistrada
{
    public function __construct(public readonly int $visitaId) {}
}
