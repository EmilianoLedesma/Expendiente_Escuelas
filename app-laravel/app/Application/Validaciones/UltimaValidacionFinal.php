<?php

namespace App\Application\Validaciones;

use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\SeccionCapacidad;
use App\Application\Validaciones\DTO\SeccionNivel;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Models\EvaluacionValidacion;

/** Re-reads the most recent stored final validation exactly as it was shown. */
class UltimaValidacionFinal
{
    /**
     * No stored evaluation, or one stored before the capacity engine (no 'capacidad' key)
     * or under an older ready rule (no current 'regla_envio', WS-7a: its lista_para_envio
     * meant something else): opening the page must run it.
     */
    public function faltaParaEscuela(int $escuelaId): bool
    {
        $resultados = EvaluacionValidacion::where('escuela_id', $escuelaId)->latest('id')->first()?->resultados;

        return $resultados === null
            || ! array_key_exists('capacidad', $resultados)
            || ($resultados['regla_envio'] ?? null) !== EjecutarValidacionFinal::REGLA_ENVIO;
    }

    public function paraEscuela(int $escuelaId): ?ValidacionFinal
    {
        $evaluacion = EvaluacionValidacion::where('escuela_id', $escuelaId)->latest('id')->first();

        if ($evaluacion === null) {
            return null;
        }

        $resultados = $evaluacion->resultados;
        // Rows stored before the capacity and per-level sections are a flat list of documental rows.
        if (array_is_list($resultados)) {
            $resultados = ['documental' => $resultados];
        }

        /** @var list<array{clave: string, titulo: string, estado: string, mensaje: string, documentos: array<string, string>, lineas: list<string>, pasoCorreccion?: string|null}> $documental */
        $documental = $resultados['documental'] ?? [];
        /** @var list<array{escuelaNivelId: int, nivel: string, filas: list<array{clave: string, titulo: string, estado: string, mensaje: string, documentos: array<string, string>, lineas: list<string>, pasoCorreccion?: string|null}>}> $capacidad */
        $capacidad = $resultados['capacidad'] ?? [];
        /** @var list<array{escuelaNivelId: int, nivel: string, filas: list<array{clave: string, titulo: string, estado: string, mensaje: string, documentos: array<string, string>, lineas: list<string>, pasoCorreccion?: string|null}>}> $niveles */
        $niveles = $resultados['niveles'] ?? [];

        return new ValidacionFinal(
            $evaluacion->id,
            $evaluacion->created_at,
            $evaluacion->lista_para_envio,
            array_map(fn (array $fila) => FilaValidacion::desdeArreglo($fila), $documental),
            array_map(fn (array $seccion) => SeccionCapacidad::desdeArreglo($seccion), $capacidad),
            array_map(fn (array $seccion) => SeccionNivel::desdeArreglo($seccion), $niveles),
        );
    }
}
