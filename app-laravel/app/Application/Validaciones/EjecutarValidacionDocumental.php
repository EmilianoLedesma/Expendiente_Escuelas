<?php

namespace App\Application\Validaciones;

use App\Domain\Validaciones\Documental\MotorValidacionDocumental;
use App\Domain\Validaciones\Documental\NormalizadorNombre;
use App\Domain\Validaciones\Documental\Reglas\CurpCoincide;
use App\Domain\Validaciones\Documental\Reglas\DocumentosRequeridosPresentes;
use App\Domain\Validaciones\Documental\Reglas\NombreTitularCoincide;
use App\Domain\Validaciones\Resultado\ReporteValidacion;

/**
 * Thin use case: build the context, run the rules, return the report.
 * Blocks nothing — whether any result gates the flow is an open owner
 * decision (PENDIENTE-motor-validacion-hechos P1). Not wired into the
 * wizard yet.
 */
class EjecutarValidacionDocumental
{
    public function __construct(private readonly ConstruirContextoValidacion $construirContexto) {}

    public function ejecutar(int $escuelaId): ReporteValidacion
    {
        $motor = new MotorValidacionDocumental([
            new DocumentosRequeridosPresentes,
            new NombreTitularCoincide(new NormalizadorNombre),
            new CurpCoincide,
        ]);

        return $motor->ejecutar($this->construirContexto->ejecutar($escuelaId));
    }
}
