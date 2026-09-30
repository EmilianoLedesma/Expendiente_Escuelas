<?php

namespace App\Application\Validaciones;

use App\Models\EvaluacionValidacion;

/** Path of a stored validation PDF, only if it belongs to that escuela; the Policy already decided who may see the escuela. */
class ReporteValidacionGuardado
{
    public function ruta(int $escuelaId, int $evaluacionId): ?string
    {
        return EvaluacionValidacion::where('escuela_id', $escuelaId)->whereKey($evaluacionId)->value('archivo_path');
    }
}
