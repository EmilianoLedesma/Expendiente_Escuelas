<?php

namespace App\Application\ResponsablesNivel;

use App\Application\Tramite\EliminarTramite;
use App\Models\EscuelaNivel;
use App\Models\ResponsableNivel;

/** Alimenta la página "Responsables por nivel": cada nivel del solicitante con sus responsables. */
final class ListarResponsablesNivel
{
    /** @return list<array{escuelaNivelId: int, etiqueta: string, asignable: bool, responsables: list<array{id: int, nombre: string, correo: string}>}> */
    public function ejecutar(int $solicitanteId): array
    {
        $niveles = EscuelaNivel::whereHas('escuela', fn ($q) => $q->where('solicitante_id', $solicitanteId))
            ->with(['escuela.plantel', 'nivelEducativo'])
            ->orderBy('escuela_id')->orderBy('id')
            ->get();
        $accesos = ResponsableNivel::with('user')->whereIn('escuela_nivel_id', $niveles->pluck('id'))->orderBy('id')->get()->groupBy('escuela_nivel_id');
        $enCaptura = EliminarTramite::idEnCaptura();

        return $niveles->map(fn (EscuelaNivel $nivel) => [
            'escuelaNivelId' => $nivel->id,
            'etiqueta' => 'Nº '.str_pad((string) $nivel->escuela_id, 4, '0', STR_PAD_LEFT).' · '.$nivel->nivelEducativo->nombre.' — '.$nivel->escuela->plantel->calle,
            'asignable' => (int) $nivel->estado_id === $enCaptura,
            'responsables' => ($accesos[$nivel->id] ?? collect())
                ->map(fn (ResponsableNivel $a) => ['id' => $a->id, 'nombre' => $a->user->name, 'correo' => $a->user->email])
                ->values()->all(),
        ])->values()->all();
    }
}
