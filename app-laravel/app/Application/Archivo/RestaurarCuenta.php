<?php

namespace App\Application\Archivo;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\Escuela;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Restaura una cuenta (solo admin) y únicamente lo que su archivo en cascada marcó con el
 * mismo lote: trámites y responsables archivados aparte (otro lote) siguen archivados.
 * Las asignaciones de revisión liberadas no se reasignan.
 *
 * Orden de bloqueo: fila users del objetivo, luego escuela_niveles y escuelas por trámite
 * (RestaurarTramite), como ArchivarCuenta.
 */
class RestaurarCuenta
{
    public function __construct(
        private readonly RestaurarTramite $restaurarTramite,
        private readonly RegistrarAccion $registrar,
    ) {}

    /** @throws PrecondicionIncumplida */
    public function ejecutar(int $actorId, int $userId): void
    {
        DB::transaction(function () use ($actorId, $userId) {
            $filas = User::whereIn('id', [$userId, $actorId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $filas->get($actorId) ?? throw new PrecondicionIncumplida('rol', 'No tienes permiso para esta acción.');
            RolesSedeq::exigir($actor, RolesSedeq::ADMIN);
            $usuario = $filas->get($userId) ?? throw new PrecondicionIncumplida('cuenta', 'La cuenta no existe.');

            $lote = $usuario->archivado_lote;
            if ($usuario->archivado_at === null) {
                throw new PrecondicionIncumplida('archivo', 'La cuenta no está archivada.');
            }
            $solicitanteId = Solicitante::where('user_id', $userId)->value('id');
            // Un responsable arrastrado comparte el lote de su solicitante: restaurarlo solo reactivaría esa cuenta.
            if ($lote !== null && $solicitanteId === null && User::where('archivado_lote', $lote)->whereKeyNot($userId)->exists()) {
                throw new PrecondicionIncumplida('archivo', 'Esta cuenta se archivó junto con la de su solicitante: restaura o elimina esa cuenta.');
            }

            $limpiar = ['archivado_at' => null, 'archivado_por' => null, 'archivado_motivo' => null, 'archivado_lote' => null];
            $usuario->forceFill($limpiar)->save();

            $tramites = 0;
            $responsables = 0;
            if ($lote !== null) {
                $responsables = User::where('archivado_lote', $lote)->update($limpiar);
            }
            // Sin solicitante no hay trámites: where('solicitante_id', null) sería IS NULL.
            if ($lote !== null && $solicitanteId !== null) {
                $escuelaIds = Escuela::withoutGlobalScope(EscuelaNoArchivada::class)
                    ->where('solicitante_id', $solicitanteId)->where('archivado_lote', $lote)->orderBy('id')->pluck('id');
                foreach ($escuelaIds as $escuelaId) {
                    $this->restaurarTramite->ejecutar($actorId, (int) $escuelaId);
                    $tramites++;
                }
            }

            $this->registrar->ejecutar($actorId, RegistrarAccion::RESTAURAR_CUENTA, 'user', $userId, null, [
                'lote' => $lote, 'tramites' => $tramites, 'responsables' => $responsables,
            ]);
        });
    }
}
