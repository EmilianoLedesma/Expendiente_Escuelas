<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosCurriculumProyecto;
use App\Models\ProyectoEducativoCurriculum;

/** Lectura de la sección 7: lo guardado del nivel ('si'/'no' por ámbito, como lo usa el formulario) y si está completa. */
class CurriculumProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (todo null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoCurriculum::where('escuela_nivel_id', $escuelaNivelId)->first();

        $datos = [];
        foreach (DatosCurriculumProyecto::campos() as $campo) {
            $valor = $fila?->getAttribute($campo);
            $datos[$campo] = is_bool($valor) ? ($valor ? 'si' : 'no') : $valor;
        }

        return $datos;
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasCurriculumProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
