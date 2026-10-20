<?php

namespace App\Application\Documentos;

/**
 * Estado de exigibilidad de un documento del catálogo para un expediente concreto. Lo decide la capa de
 * aplicación a partir de datos del servidor; el solicitante no puede cambiarlo.
 */
enum Aplicabilidad: string
{
    case Obligatorio = 'obligatorio';

    /** Hay evidencia suficiente de que el supuesto que lo exige no se presenta. Se puede cargar igualmente. */
    case NoRequerido = 'no_requerido';

    /** No se puede decidir (faltan niveles o evidencia): mientras tanto se trata como exigible. */
    case PendienteDeterminar = 'pendiente_determinar';

    /** ¿Cuenta para la completitud de Paso 2.2? Solo `NoRequerido` queda fuera. */
    public function exigeCarga(): bool
    {
        return $this !== self::NoRequerido;
    }
}
