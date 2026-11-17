<?php

namespace App\Application\ProyectoEducativo;

use App\Models\ProyectoEducativoCalendarioAcademico;

/** Lectura de la sección 18: lo guardado del nivel (descripción y metadatos del PDF) y si está completa. */
class CalendarioAcademicoProyectoCapturada
{
    /** @return array<string, string|int|null> campo => valor (todo null si aún no hay fila); nunca expone la ruta de disco */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoCalendarioAcademico::where('escuela_nivel_id', $escuelaNivelId)->first();

        return [
            'descripcion_calendario' => $fila?->descripcion,
            'archivo_nombre' => $fila?->archivo_nombre,
            'archivo_paginas' => $fila?->archivo_paginas,
        ];
    }

    public function completa(int $escuelaNivelId): bool
    {
        $fila = ProyectoEducativoCalendarioAcademico::where('escuela_nivel_id', $escuelaNivelId)->first();

        return $fila !== null
            && $fila->archivo_path !== null
            && ReglasCalendarioAcademicoProyecto::erroresDescripcion($fila->descripcion) === [];
    }
}
