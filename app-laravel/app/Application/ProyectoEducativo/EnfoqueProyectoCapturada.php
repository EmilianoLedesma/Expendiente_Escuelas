<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosEnfoqueProyecto;
use App\Models\ProyectoEducativoEnfoque;

/** Lectura de la sección 4: lo guardado del nivel y si está completa (mismas reglas que el guardado). */
class EnfoqueProyectoCapturada
{
    /** @return array<string, string|list<string>|null> campo => valor (todo null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoEnfoque::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosEnfoqueProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosEnfoqueProyecto::campos()),
        );
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasEnfoqueProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
