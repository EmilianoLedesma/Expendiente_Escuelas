<?php

namespace App\Application\ResponsablesNivel;

use App\Models\ResponsableNivel;

/** Alimenta "Mis niveles asignados" del responsable. */
final class ListarNivelesDelResponsable
{
    /** @return list<array{escuelaId: int, escuelaNivelId: int, nivel: string, etiqueta: string}> */
    public function ejecutar(int $userId): array
    {
        return ResponsableNivel::with(['escuelaNivel.escuela.plantel', 'escuelaNivel.nivelEducativo'])
            ->where('user_id', $userId)->orderBy('id')->get()
            ->map(fn (ResponsableNivel $a) => [
                'escuelaId' => $a->escuelaNivel->escuela_id,
                'escuelaNivelId' => $a->escuelaNivel->id,
                'nivel' => $a->escuelaNivel->nivelEducativo->nombre,
                'etiqueta' => 'Nº '.str_pad((string) $a->escuelaNivel->escuela_id, 4, '0', STR_PAD_LEFT).' — '.$a->escuelaNivel->escuela->plantel->calle,
            ])->values()->all();
    }
}
