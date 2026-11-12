<?php

namespace App\Application\ProyectoEducativo;

use App\Models\ProyectoEducativoPaec;

/** Lectura de la sección 6: lo guardado del nivel (texto y metadatos de los PDF) y si está completa. */
class PaecProyectoCapturada
{
    /** @return array<string, string|int|null> campo => valor (todo null si aún no hay fila); nunca expone las rutas de disco */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoPaec::where('escuela_nivel_id', $escuelaNivelId)->first();

        return [
            'diagnostico' => $fila?->diagnostico,
            'hoja_ruta_nombre' => $fila?->hoja_ruta_nombre,
            'hoja_ruta_paginas' => $fila?->hoja_ruta_paginas,
            'planeacion_nombre' => $fila?->planeacion_nombre,
            'planeacion_paginas' => $fila?->planeacion_paginas,
        ];
    }

    public function completa(int $escuelaNivelId): bool
    {
        $fila = ProyectoEducativoPaec::where('escuela_nivel_id', $escuelaNivelId)->first();

        return $fila !== null
            && $fila->hoja_ruta_path !== null
            && $fila->planeacion_path !== null
            && ReglasPaecProyecto::erroresDiagnostico($fila->diagnostico) === [];
    }
}
