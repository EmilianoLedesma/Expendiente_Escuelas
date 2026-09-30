<?php

namespace App\Application\Validaciones;

use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Models\EvaluacionValidacion;

/** Re-reads the most recent stored final validation exactly as it was shown. */
class UltimaValidacionFinal
{
    public function paraEscuela(int $escuelaId): ?ValidacionFinal
    {
        $evaluacion = EvaluacionValidacion::where('escuela_id', $escuelaId)->latest('id')->first();

        if ($evaluacion === null) {
            return null;
        }

        return new ValidacionFinal(
            $evaluacion->id,
            $evaluacion->created_at,
            $evaluacion->lista_para_envio,
            array_map(fn (array $fila) => FilaValidacion::desdeArreglo($fila), array_values($evaluacion->resultados)),
        );
    }
}
