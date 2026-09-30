<?php

namespace App\Application\Validaciones;

use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\SeccionCapacidad;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Models\EvaluacionValidacion;

/** Re-reads the most recent stored final validation exactly as it was shown. */
class UltimaValidacionFinal
{
    public function paraEscuela(int $escuelaId): ?ValidacionFinal
    {
        $evaluacion = EvaluacionValidacion::where('escuela_id', $escuelaId)->latest('id')->first();

        if ($evaluacion === null) {
            return null;
        }

        $resultados = $evaluacion->resultados;
        // Rows stored before the capacity section existed are a flat list of documental rows.
        /** @var list<array{clave: string, titulo: string, estado: string, mensaje: string, documentos: array<string, string>, lineas: list<string>, pasoCorreccion?: string|null}> $documental */
        $documental = array_is_list($resultados) ? $resultados : ($resultados['documental'] ?? []);
        /** @var list<array{escuelaNivelId: int, nivel: string, filas: list<array{clave: string, titulo: string, estado: string, mensaje: string, documentos: array<string, string>, lineas: list<string>, pasoCorreccion?: string|null}>}> $capacidad */
        $capacidad = array_is_list($resultados) ? [] : ($resultados['capacidad'] ?? []);

        return new ValidacionFinal(
            $evaluacion->id,
            $evaluacion->created_at,
            $evaluacion->lista_para_envio,
            array_map(fn (array $fila) => FilaValidacion::desdeArreglo($fila), $documental),
            array_map(fn (array $seccion) => SeccionCapacidad::desdeArreglo($seccion), $capacidad),
        );
    }
}
