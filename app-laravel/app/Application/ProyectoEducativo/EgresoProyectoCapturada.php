<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosEgresoProyecto;
use App\Models\ProyectoEducativoEgreso;

/** Lectura de la sección 9: lo guardado del nivel y si está completa (mismas reglas que el guardado). */
class EgresoProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoEgreso::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosEgresoProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosEgresoProyecto::campos()),
        );
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasEgresoProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
