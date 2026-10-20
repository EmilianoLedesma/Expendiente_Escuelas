<?php

namespace App\Application\Validaciones;

use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;

/** Path of a stored validation PDF, only if it belongs to that escuela; the Policy already decided who may see the escuela. */
class ReporteValidacionGuardado
{
    public function ruta(int $escuelaId, int $evaluacionId): ?string
    {
        return EvaluacionValidacion::where('escuela_id', $escuelaId)->whereKey($evaluacionId)->value('archivo_path');
    }

    /**
     * Ruta del reporte con que se envió el trámite: la última evaluación de una escuela ya enviada (tras el envío no se
     * guardan más, EjecutarValidacionFinal::ejecutar exige un trámite editable). null si la escuela no está enviada (algún nivel en captura o sin niveles).
     */
    public function rutaEnviada(int $escuelaId): ?string
    {
        if (! TramiteEditable::enviado(EscuelaNivel::where('escuela_id', $escuelaId)->pluck('estado_id'), TramiteEditable::idEnCaptura())) {
            return null;
        }

        return EvaluacionValidacion::where('escuela_id', $escuelaId)->latest('id')->value('archivo_path');
    }
}
