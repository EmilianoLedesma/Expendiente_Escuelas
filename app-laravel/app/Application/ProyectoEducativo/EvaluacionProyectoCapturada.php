<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosEvaluacionProyecto;
use App\Models\ProyectoEducativoEvaluacion;

/** Lectura de la sección 5: lo guardado del nivel y si está completa (mismas reglas que el guardado). */
class EvaluacionProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (todo null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoEvaluacion::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosEvaluacionProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosEvaluacionProyecto::campos()),
        );
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasEvaluacionProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
