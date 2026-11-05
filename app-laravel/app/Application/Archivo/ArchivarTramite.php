<?php

namespace App\Application\Archivo;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\DesasignarTramite;
use App\Application\Revision\RolesSedeq;
use App\Application\Tramite\TramiteEditable;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Scopes\NivelDeEscuelaNoArchivada;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Archiva un trámite (borrado lógico): marca la escuela, conserva filas y archivos,
 * libera sus asignaciones de revisión y deja fila de auditoría. El solicitante solo
 * puede mientras todos sus niveles sigan en captura (la propiedad la decide
 * EscuelaPolicy::delete antes de llegar aquí); el admin, en cualquier estado y con motivo opcional (queda la traza fechada).
 * El borrado definitivo es solo la purga del admin.
 */
class ArchivarTramite
{
    public function __construct(
        private readonly DesasignarTramite $desasignar,
        private readonly RegistrarAccion $registrar,
    ) {}

    /** @throws PrecondicionIncumplida */
    public function ejecutar(int $escuelaId, int $actorId, ?string $motivo = null, ?string $lote = null): void
    {
        DB::transaction(function () use ($escuelaId, $actorId, $motivo, $lote) {
            $esAdmin = User::findOrFail($actorId)->hasRole(RolesSedeq::ADMIN);
            // Orden de bloqueo del proyecto: escuela_niveles antes que escuelas.
            $niveles = EscuelaNivel::withoutGlobalScope(NivelDeEscuelaNoArchivada::class)
                ->where('escuela_id', $escuelaId)->orderBy('id')->lockForUpdate()->pluck('estado_id', 'id');
            $escuela = Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->lockForUpdate()->findOrFail($escuelaId);

            if ($escuela->archivado_at !== null) {
                throw new PrecondicionIncumplida('archivo', 'El trámite ya está archivado.');
            }
            if (! $esAdmin && ! TramiteEditable::todosEnCaptura($niveles, TramiteEditable::idEnCaptura())) {
                throw new PrecondicionIncumplida(TramiteEditable::EN_CAPTURA, 'Solo se puede archivar un trámite mientras todos sus niveles estén en captura.');
            }

            $avisar = $esAdmin && $lote === null; // en cascada de cuenta (lote dado) o si archiva el dueño no hay correo
            $lote ??= (string) Str::uuid();
            $escuela->forceFill([
                'archivado_at' => now(),
                'archivado_por' => $actorId,
                'archivado_motivo' => $motivo,
                'archivado_lote' => $lote,
            ])->save();

            $this->desasignar->liberar($actorId, $niveles->keys()->map(fn ($id) => (int) $id)->values()->all(), 'desasignar_archivo');
            $this->registrar->ejecutar($actorId, RegistrarAccion::ARCHIVAR_TRAMITE, 'escuela', $escuelaId, $motivo, ['lote' => $lote]);
            if ($avisar) {
                DB::afterCommit(fn () => event(new TramiteCerradoPorAdmin($escuelaId)));
            }
        });
    }
}
