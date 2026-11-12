<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosAcademiasProyecto;
use App\Models\ProyectoEducativoAcademias;

/** Lectura de la sección 12: lo guardado del nivel y si está completa (mismas reglas que el guardado). */
class AcademiasProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoAcademias::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosAcademiasProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosAcademiasProyecto::campos()),
        );
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasAcademiasProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
