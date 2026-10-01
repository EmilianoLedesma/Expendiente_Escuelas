<?php

namespace App\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\ReglaDocumental;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ResultadoRegla;

/**
 * Mirrors DocumentosCompletos into the report; it does not redefine which
 * documents apply — the context carries that list from the catalog.
 */
final readonly class DocumentosRequeridosPresentes implements ReglaDocumental
{
    public const CLAVE = 'documentos_requeridos_presentes';

    /** Same check over one escuela_nivel's Paso 2.4 checklist. */
    public const CLAVE_NIVEL = 'documentos_nivel_presentes';

    public function __construct(private string $clave = self::CLAVE) {}

    public function evaluar(ContextoValidacion $contexto): ResultadoRegla
    {
        if ($contexto->clavesRequeridas === []) {
            return new ResultadoRegla($this->clave, EstadoResultado::NoEvaluable, 'No hay catálogo de documentos requeridos.');
        }

        $faltantes = array_values(array_diff($contexto->clavesRequeridas, $contexto->clavesPresentes));

        return $faltantes === []
            ? new ResultadoRegla($this->clave, EstadoResultado::Cumple, 'Todos los documentos requeridos están cargados.')
            : new ResultadoRegla($this->clave, EstadoResultado::NoCumple, 'Faltan documentos requeridos.', ['faltantes' => $faltantes], $faltantes);
    }
}
