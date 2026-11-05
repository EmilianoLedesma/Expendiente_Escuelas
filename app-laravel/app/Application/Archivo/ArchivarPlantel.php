<?php

namespace App\Application\Archivo;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Archiva un plantel (solo admin): un plantel solo existe a través de sus trámites, así que
 * archiva en un solo lote todos los trámites activos (ArchivarTramite) y deja una fila de
 * auditoría con ese lote. Un plantel cuenta como archivado cuando ya no tiene trámites activos.
 */
class ArchivarPlantel
{
    public function __construct(
        private readonly ArchivarTramite $archivarTramite,
        private readonly RegistrarAccion $registrar,
    ) {}

    /** @throws PrecondicionIncumplida si el plantel no tiene trámites activos. */
    public function ejecutar(int $plantelId, int $actorId, ?string $motivo = null): void
    {
        DB::transaction(function () use ($plantelId, $actorId, $motivo) {
            RolesSedeq::exigir(User::findOrFail($actorId), RolesSedeq::ADMIN);
            Plantel::whereKey($plantelId)->lockForUpdate()->firstOrFail();

            $ids = Escuela::withoutGlobalScope(EscuelaNoArchivada::class)
                ->where('plantel_id', $plantelId)->whereNull('archivado_at')->orderBy('id')->pluck('id');
            if ($ids->isEmpty()) {
                throw new PrecondicionIncumplida('archivo', 'El plantel no tiene trámites activos.');
            }

            $lote = (string) Str::uuid();
            foreach ($ids as $id) {
                $this->archivarTramite->ejecutar((int) $id, $actorId, $motivo, $lote);
            }
            $this->registrar->ejecutar($actorId, RegistrarAccion::ARCHIVAR_PLANTEL, 'plantel', $plantelId, $motivo, ['lote' => $lote, 'tramites' => $ids->count()]);
        });
    }
}
