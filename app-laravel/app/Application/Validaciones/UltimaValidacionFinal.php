<?php

namespace App\Application\Validaciones;

use App\Application\Tramite\EnviarTramite;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\SeccionCapacidad;
use App\Application\Validaciones\DTO\SeccionNivel;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Models\EvaluacionValidacion;
use Illuminate\Support\Facades\DB;

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

    /** La última evaluación guardada (también la línea base del cierre de una ronda: la lee el G6, ResultadosCambiados). */
    public function paraEscuela(int $escuelaId): ?ValidacionFinal
    {
        return $this->desde(EvaluacionValidacion::where('escuela_id', $escuelaId)->latest('id')->first());
    }

    /** La evaluación con que se envió el trámite (idEnviada), no la línea base de una ronda posterior. */
    public function enviadaParaEscuela(int $escuelaId): ?ValidacionFinal
    {
        $id = $this->idEnviada($escuelaId);

        return $id === null ? null : $this->desde(EvaluacionValidacion::find($id));
    }

    /**
     * Id de la evaluación guardada al envío: la última creada hasta el primer paso a en_revision de la escuela
     * (EnviarTramite la evalúa antes de escribir ese historial y la guarda en la misma transacción). Las líneas base de
     * cada cierre de ronda (DecidirPaso) son posteriores. Sin ese historial (datos previos), la última.
     */
    public function idEnviada(int $escuelaId): ?int
    {
        $envio = DB::table('historial_estados_expediente')
            ->join('escuela_niveles', 'escuela_niveles.id', '=', 'historial_estados_expediente.escuela_nivel_id')
            ->join('estados_expediente', 'estados_expediente.id', '=', 'historial_estados_expediente.estado_id')
            ->where('escuela_niveles.escuela_id', $escuelaId)
            ->where('estados_expediente.clave', EnviarTramite::EN_REVISION)
            ->min('historial_estados_expediente.fecha');

        $id = EvaluacionValidacion::where('escuela_id', $escuelaId)
            // Al segundo: created_at (generadaEn) puede guardar microsegundos que la fecha del historial no.
            ->when($envio !== null, fn ($q) => $q->whereRaw("date_trunc('second', created_at) <= date_trunc('second', ?::timestamp)", [$envio]))
            ->max('id');

        return $id === null ? null : (int) $id;
    }

    private function desde(?EvaluacionValidacion $evaluacion): ?ValidacionFinal
    {
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
