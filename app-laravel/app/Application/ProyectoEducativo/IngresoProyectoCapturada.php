<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosIngresoProyecto;
use App\Models\ProyectoEducativoIngreso;

/** Lectura de la sección 8: lo guardado del nivel y si está completa (mismas reglas que el guardado). */
class IngresoProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (todo null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoIngreso::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosIngresoProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosIngresoProyecto::campos()),
        );
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasIngresoProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
