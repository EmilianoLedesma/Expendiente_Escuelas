<?php

namespace App\Application\Validaciones;

use App\Domain\Validaciones\Documental\CatalogoReglasDocumentales;
use App\Domain\Validaciones\Documental\MotorValidacionDocumental;
use App\Domain\Validaciones\Resultado\ReporteValidacion;

/**
 * Thin use case: build the context, run the rules, return the report.
 * Blocks nothing itself; EjecutarValidacionFinal decides what gates
 * sending (ADR-007).
 */
class EjecutarValidacionDocumental
{
    public function __construct(private readonly ConstruirContextoValidacion $construirContexto) {}

    public function ejecutar(int $escuelaId): ReporteValidacion
    {
        $motor = new MotorValidacionDocumental(CatalogoReglasDocumentales::reglas());

        return $motor->ejecutar($this->construirContexto->ejecutar($escuelaId));
    }

    /** Paso 2.4 documents of one escuela_nivel, with only the rules that apply to its level. */
    public function paraNivel(int $escuelaNivelId): ReporteValidacion
    {
        $contexto = $this->construirContexto->paraNivel($escuelaNivelId);

        return (new MotorValidacionDocumental(CatalogoReglasDocumentales::reglasDeNivel($contexto->clavesRequeridas)))->ejecutar($contexto);
    }
}
