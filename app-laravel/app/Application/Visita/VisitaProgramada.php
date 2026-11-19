<?php

namespace App\Application\Visita;

/** El supervisor programó una visita de inspección para un nivel. */
final class VisitaProgramada
{
    public function __construct(public readonly int $visitaId) {}
}
