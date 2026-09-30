<?php

namespace App\Domain\Validaciones\Documental;

use App\Domain\Validaciones\Resultado\ReporteValidacion;

/**
 * Runs every rule against one context, in order. Not the Motor de
 * Validación de Capacidad Instalada (Engine/ValidacionCapacidadService):
 * different trigger point and rule source — see the evaluation report §2.
 */
final readonly class MotorValidacionDocumental
{
    /** @param list<ReglaDocumental> $reglas */
    public function __construct(private array $reglas) {}

    public function ejecutar(ContextoValidacion $contexto): ReporteValidacion
    {
        return new ReporteValidacion(array_map(
            fn (ReglaDocumental $regla) => $regla->evaluar($contexto),
            $this->reglas,
        ));
    }
}
