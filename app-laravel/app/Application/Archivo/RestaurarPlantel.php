<?php

namespace App\Application\Archivo;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\BitacoraAccion;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Restaura un plantel (solo admin): devuelve los trámites del último lote que archivó
 * ArchivarPlantel. Los que se archivaron por separado (dueño, cuenta) siguen archivados.
 */
class RestaurarPlantel
{
    public function __construct(
        private readonly RestaurarTramite $restaurarTramite,
        private readonly RegistrarAccion $registrar,
    ) {}

    /** @throws PrecondicionIncumplida si no hay un archivo de plantel que revertir. */
    public function ejecutar(int $actorId, int $plantelId): void
    {
        DB::transaction(function () use ($actorId, $plantelId) {
            RolesSedeq::exigir(User::findOrFail($actorId), RolesSedeq::ADMIN);
            Plantel::whereKey($plantelId)->lockForUpdate()->firstOrFail();

            $lote = BitacoraAccion::where('accion', RegistrarAccion::ARCHIVAR_PLANTEL)
                ->where('objeto_tipo', 'plantel')->where('objeto_id', $plantelId)
                ->orderByDesc('id')->first()?->detalle['lote'] ?? null;
            $ids = $lote === null ? collect() : Escuela::withoutGlobalScope(EscuelaNoArchivada::class)
                ->where('plantel_id', $plantelId)->where('archivado_lote', $lote)->whereNotNull('archivado_at')
                ->orderBy('id')->pluck('id');
            if ($ids->isEmpty()) {
                throw new PrecondicionIncumplida('archivo', 'El plantel no tiene trámites archivados con él que restaurar.');
            }

            foreach ($ids as $id) {
                $this->restaurarTramite->ejecutar($actorId, (int) $id);
            }
            $this->registrar->ejecutar($actorId, RegistrarAccion::RESTAURAR_PLANTEL, 'plantel', $plantelId, null, ['lote' => $lote, 'tramites' => $ids->count()]);
        });
    }
}
