<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosObjetivosProyecto;
use App\Models\ProyectoEducativoObjetivos;

/** Lectura de la sección 3: lo guardado del nivel y si está completa (mismas reglas que el guardado). */
class ObjetivosProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (todo null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoObjetivos::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosObjetivosProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosObjetivosProyecto::campos()),
        );
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasObjetivosProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
