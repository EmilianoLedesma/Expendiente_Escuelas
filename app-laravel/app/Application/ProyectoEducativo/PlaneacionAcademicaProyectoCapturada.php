<?php

namespace App\Application\ProyectoEducativo;

use App\Models\ProyectoEducativoPlaneacionAcademica;

/** Lectura de la sección 17: lo guardado del nivel (descripción y metadatos del PDF) y si está completa. */
class PlaneacionAcademicaProyectoCapturada
{
    /** @return array<string, string|int|null> campo => valor (todo null si aún no hay fila); nunca expone la ruta de disco */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoPlaneacionAcademica::where('escuela_nivel_id', $escuelaNivelId)->first();

        return [
            'descripcion_planeacion' => $fila?->descripcion,
            'archivo_nombre' => $fila?->archivo_nombre,
            'archivo_paginas' => $fila?->archivo_paginas,
        ];
    }

    public function completa(int $escuelaNivelId): bool
    {
        $fila = ProyectoEducativoPlaneacionAcademica::where('escuela_nivel_id', $escuelaNivelId)->first();

        // La fila existe solo con PDF y descripción (las columnas son NOT NULL): basta revisar que la descripción cumpla.
        return $fila !== null
            && ReglasPlaneacionAcademicaProyecto::erroresDescripcion($fila->descripcion) === [];
    }
}
