<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosFormacionLaboralProyecto;
use App\Models\ProyectoEducativoFormacionLaboral;

/** Lectura de la sección 14: lo guardado del nivel y si está completa (mismas reglas que el guardado). */
class FormacionLaboralProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoFormacionLaboral::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosFormacionLaboralProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosFormacionLaboralProyecto::campos()),
        );
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasFormacionLaboralProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
