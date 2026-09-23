<?php

namespace App\Application\Preregistro;

use InvalidArgumentException;

/**
 * El plantel elegido en bifurcación "existente" no es del solicitante (o no
 * existe). El mensaje no distingue ambos casos a propósito.
 */
final class PlantelNoDisponible extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('El plantel seleccionado no está disponible.');
    }
}
