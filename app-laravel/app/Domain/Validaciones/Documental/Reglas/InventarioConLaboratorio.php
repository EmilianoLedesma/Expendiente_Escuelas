<?php

namespace App\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\ReglaDocumental;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ResultadoRegla;

/**
 * A laboratory inventory implies a declared laboratory in the plantel's
 * infrastructure. Missing one is an alert, not a block: the capacity
 * engine owns whether a laboratory is required.
 */
final readonly class InventarioConLaboratorio implements ReglaDocumental
{
    public const CLAVE = 'inventario_con_laboratorio';

    public const DOCUMENTO = 'inventario_laboratorio';

    public function evaluar(ContextoValidacion $contexto): ResultadoRegla
    {
        if (! $contexto->presente(self::DOCUMENTO)) {
            return new ResultadoRegla(self::CLAVE, EstadoResultado::NoEvaluable, 'No se ha cargado el inventario del laboratorio.');
        }

        $laboratorios = (int) ($contexto->declarado(TipoHecho::LaboratoriosDeclarados)->valor ?? '0');

        return $laboratorios > 0
            ? new ResultadoRegla(self::CLAVE, EstadoResultado::Cumple, 'Hay laboratorio declarado en la infraestructura.', ['laboratorios' => $laboratorios])
            : new ResultadoRegla(self::CLAVE, EstadoResultado::Advertencia, 'Se cargó un inventario de laboratorio, pero no hay laboratorio declarado en la infraestructura.', ['laboratorios' => 0], [self::DOCUMENTO]);
    }
}
