<?php

namespace App\Application\ProyectoEducativo;

use App\Models\ProyectoEducativoProgramasEstudios;

/** Lectura de la sección 16: lo guardado del nivel (respuestas, justificación y metadatos del PDF) y si está completa. */
class ProgramasEstudiosProyectoCapturada
{
    /** @return array<string, string|int|null> campo => valor (todo null si aún no hay fila); nunca expone la ruta de disco */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoProgramasEstudios::where('escuela_nivel_id', $escuelaNivelId)->first();

        return [
            'periodicidad_ciclo' => $fila?->periodicidad,
            'periodicidad_otra' => $fila?->periodicidad_otra,
            'modalidad_programas' => $fila?->modalidad,
            'retoma_dgb' => $fila === null ? null : ($fila->retoma_dgb ? 'si' : 'no'),
            'justificacion_adaptacion' => $fila?->justificacion,
            'archivo_nombre' => $fila?->archivo_nombre,
            'archivo_paginas' => $fila?->archivo_paginas,
        ];
    }

    public function completa(int $escuelaNivelId): bool
    {
        $fila = ProyectoEducativoProgramasEstudios::where('escuela_nivel_id', $escuelaNivelId)->first();

        return $fila !== null
            && ReglasProgramasEstudiosProyecto::errores($this->datos($escuelaNivelId)) === []
            && ($fila->retoma_dgb || $fila->archivo_path !== null);
    }
}
