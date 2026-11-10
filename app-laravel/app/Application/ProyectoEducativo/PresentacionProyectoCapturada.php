<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosPresentacionProyecto;
use App\Models\ProyectoEducativoPresentacion;

/** Lectura de la sección 1: lo guardado del nivel y si está completa (mismas reglas que el guardado). */
class PresentacionProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (todo null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoPresentacion::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosPresentacionProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosPresentacionProyecto::campos()),
        );
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasPresentacionProyecto::errores($this->datos($escuelaNivelId), completa: true) === [];
    }
}
