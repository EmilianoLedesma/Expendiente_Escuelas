<?php

namespace App\Application\Excepciones;

use DomainException;

/**
 * Un caso de uso se invocó antes de que su etapa previa estuviera completa.
 * $etapaFaltante le dice al adaptador (Livewire, API) a dónde mandar al
 * usuario, sin que tenga que recalcular la precondición.
 */
final class PrecondicionIncumplida extends DomainException
{
    public function __construct(public readonly string $etapaFaltante, string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
