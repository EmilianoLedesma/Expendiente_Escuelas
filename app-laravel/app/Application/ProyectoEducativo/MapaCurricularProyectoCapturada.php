<?php

namespace App\Application\ProyectoEducativo;

use App\Models\ProyectoEducativoMapaCurricular;

/** Lectura de la sección 15: lo guardado del nivel (descripción y metadatos del PDF) y si está completa. */
class MapaCurricularProyectoCapturada
{
    /** @return array<string, string|int|null> campo => valor (todo null si aún no hay fila); nunca expone la ruta de disco */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoMapaCurricular::where('escuela_nivel_id', $escuelaNivelId)->first();

        return [
            'descripcion_mapa' => $fila?->descripcion,
            'archivo_nombre' => $fila?->archivo_nombre,
            'archivo_paginas' => $fila?->archivo_paginas,
        ];
    }

    public function completa(int $escuelaNivelId): bool
    {
        $fila = ProyectoEducativoMapaCurricular::where('escuela_nivel_id', $escuelaNivelId)->first();

        return $fila !== null
            && $fila->archivo_path !== null
            && ReglasMapaCurricularProyecto::erroresDescripcion($fila->descripcion) === [];
    }
}
