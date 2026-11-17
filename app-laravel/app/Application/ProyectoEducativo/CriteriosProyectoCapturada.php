<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosCriteriosProyecto;
use App\Models\ProyectoEducativoCriterios;

/** Lectura de la sección 13: lo guardado del nivel y si está completa (los cinco campos cumplen las reglas). */
class CriteriosProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (null si aún no hay fila o su bloque no se ha guardado) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoCriterios::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosCriteriosProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosCriteriosProyecto::campos()),
        );
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasCriteriosProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
