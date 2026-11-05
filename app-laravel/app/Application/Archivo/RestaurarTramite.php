<?php

namespace App\Application\Archivo;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Scopes\NivelDeEscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Restaura un trámite archivado (solo admin). No reasigna revisión: las asignaciones
 * liberadas al archivar no se recuperan. Si la cuenta del dueño sigue archivada se
 * rechaza: primero se restaura la cuenta.
 */
class RestaurarTramite
{
    public function __construct(private readonly RegistrarAccion $registrar) {}

    /** @throws PrecondicionIncumplida */
    /** $avisar: correo al dueño; la cascada de RestaurarCuenta no lo pide. */
    public function ejecutar(int $actorId, int $escuelaId, bool $avisar = false): void
    {
        DB::transaction(function () use ($actorId, $escuelaId, $avisar) {
            RolesSedeq::exigir(User::findOrFail($actorId), RolesSedeq::ADMIN);

            // Orden de bloqueo del proyecto: escuela_niveles antes que escuelas.
            EscuelaNivel::withoutGlobalScope(NivelDeEscuelaNoArchivada::class)
                ->where('escuela_id', $escuelaId)->orderBy('id')->lockForUpdate()->pluck('id');
            $escuela = Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->lockForUpdate()->findOrFail($escuelaId);

            if ($escuela->archivado_at === null) {
                throw new PrecondicionIncumplida('archivo', 'El trámite no está archivado.');
            }
            $dueno = User::find(Solicitante::whereKey($escuela->solicitante_id)->value('user_id'));
            if ($dueno?->archivado_at !== null) {
                throw new PrecondicionIncumplida('archivo', 'La cuenta del solicitante está archivada: restáurala primero.');
            }

            $escuela->forceFill([
                'archivado_at' => null,
                'archivado_por' => null,
                'archivado_motivo' => null,
                'archivado_lote' => null,
            ])->save();

            $this->registrar->ejecutar($actorId, RegistrarAccion::RESTAURAR_TRAMITE, 'escuela', $escuelaId);
            if ($avisar) {
                DB::afterCommit(fn () => event(new TramiteReabiertoPorAdmin($escuelaId)));
            }
        });
    }
}
