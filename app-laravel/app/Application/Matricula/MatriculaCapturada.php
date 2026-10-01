<?php

namespace App\Application\Matricula;

use App\Models\EscuelaNivel;
use App\Models\Grado;
use App\Models\MatriculaGrado;
use App\Models\MatriculaSala;
use App\Models\Sala;

/** Read side of Paso 3 Matrícula: which shape the level uses, prefill and catalogs. */
class MatriculaCapturada
{
    public function porSala(int $escuelaNivelId): bool
    {
        return EscuelaNivel::with('nivelEducativo')->findOrFail($escuelaNivelId)->nivelEducativo->clave === 'inicial';
    }

    /** @return array<int, string> sala_id => alumnos, every sala present (empty string when not captured) */
    public function salas(int $escuelaNivelId): array
    {
        $capturadas = MatriculaSala::where('escuela_nivel_id', $escuelaNivelId)->pluck('cantidad_alumnos', 'sala_id');

        return Sala::orderBy('orden')->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => isset($capturadas[$id]) ? (string) $capturadas[$id] : ''])
            ->all();
    }

    /** @return list<array{gradoId: string, grupo: string, alumnos: string}> */
    public function grupos(int $escuelaNivelId): array
    {
        return MatriculaGrado::where('escuela_nivel_id', $escuelaNivelId)
            ->join('grados', 'grados.id', '=', 'matricula_grados.grado_id')
            ->orderBy('grados.orden')->orderBy('matricula_grados.grupo')
            ->get(['matricula_grados.grado_id', 'matricula_grados.grupo', 'matricula_grados.cantidad_alumnos'])
            ->map(fn (MatriculaGrado $g) => ['gradoId' => (string) $g->grado_id, 'grupo' => $g->grupo, 'alumnos' => (string) $g->cantidad_alumnos])
            ->values()->all();
    }

    /** @return array<int, string> */
    public function grados(int $escuelaNivelId): array
    {
        $nivelId = EscuelaNivel::whereKey($escuelaNivelId)->value('nivel_educativo_id');

        return Grado::where('nivel_educativo_id', $nivelId)->orderBy('orden')->pluck('nombre', 'id')->all();
    }

    /** @return array<int, string> */
    public function nombresSalas(): array
    {
        return Sala::orderBy('orden')->pluck('nombre', 'id')->all();
    }
}
