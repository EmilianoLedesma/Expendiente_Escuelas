<?php

namespace App\View\Components\Tramite;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Component;

/**
 * Progress indicator for the trámite wizard. Reads `pasos_captura` (and,
 * given an escuela_nivel_id, `escuela_nivel_pasos`) here in the component
 * class — not in the .blade.php, which stays pure markup. Read-only display
 * data, so no Application use case; see ADR-001 and the class boundary note
 * in docs/reports/2026-09-07-paso1-preregistro.md.
 */
class Progreso extends Component
{
    /** @var Collection<int, object{id: int, nombre: string, orden: int, estado: string}> */
    public readonly Collection $pasos;

    public function __construct(public readonly ?int $escuelaNivelId = null)
    {
        $catalogo = DB::table('pasos_captura')->orderBy('orden')->get();

        $estadosPorPaso = $this->escuelaNivelId
            ? DB::table('escuela_nivel_pasos')
                ->where('escuela_nivel_id', $this->escuelaNivelId)
                ->pluck('estado', 'paso_captura_id')
            : collect();

        $this->pasos = $catalogo->map(fn ($paso) => (object) [
            'id' => $paso->id,
            'nombre' => $paso->nombre,
            'orden' => $paso->orden,
            'estado' => $estadosPorPaso->get($paso->id, 'pendiente'),
        ]);
    }

    public function render(): View
    {
        return view('components.tramite.progreso');
    }
}
