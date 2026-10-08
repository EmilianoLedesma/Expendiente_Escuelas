<?php

namespace App\Application\ResponsablesNivel;

use App\Application\Tramite\TramiteEditable;
use App\Models\ResponsableNivel;

/** Alimenta "Mis niveles asignados" del responsable. */
final class ListarNivelesDelResponsable
{
    /** @return list<array{escuelaId: int, escuelaNivelId: int, nivel: string, etiqueta: string, enviado: bool}> */
    public function ejecutar(int $userId): array
    {
        $enCaptura = TramiteEditable::idEnCaptura();

        return ResponsableNivel::with(['escuelaNivel.escuela.plantel', 'escuelaNivel.nivelEducativo'])
            ->where('user_id', $userId)->orderBy('id')->get()
            ->map(fn (ResponsableNivel $a) => [
                'escuelaId' => $a->escuelaNivel->escuela_id,
                'escuelaNivelId' => $a->escuelaNivel->id,
                'nivel' => $a->escuelaNivel->nivelEducativo->nombre,
                'etiqueta' => 'Nº '.str_pad((string) $a->escuelaNivel->escuela_id, 4, '0', STR_PAD_LEFT).' — '.$a->escuelaNivel->escuela->plantel->calle,
                // WS-7a: el nivel ya salió de captura (el trámite se envió a SEDEQ).
                'enviado' => TramiteEditable::enviado(collect([$a->escuelaNivel->estado_id]), $enCaptura),
            ])->values()->all();
    }
}
