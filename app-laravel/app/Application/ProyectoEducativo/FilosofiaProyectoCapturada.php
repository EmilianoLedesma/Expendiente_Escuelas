<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosFilosofiaProyecto;
use App\Models\ProyectoEducativoFilosofia;

/** Lectura de la sección 2: lo guardado del nivel y si está completa (mismas reglas que el guardado). */
class FilosofiaProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (todo null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoFilosofia::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosFilosofiaProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosFilosofiaProyecto::campos()),
        );
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasFilosofiaProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
