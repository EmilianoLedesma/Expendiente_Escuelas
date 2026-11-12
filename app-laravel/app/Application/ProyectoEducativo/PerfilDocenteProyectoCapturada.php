<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosPerfilDocenteProyecto;
use App\Models\ProyectoEducativoPerfilDocente;

/** Lectura de la sección 10: lo guardado del nivel y si está completa (mismas reglas que el guardado). */
class PerfilDocenteProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoPerfilDocente::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosPerfilDocenteProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosPerfilDocenteProyecto::campos()),
        );
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasPerfilDocenteProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
