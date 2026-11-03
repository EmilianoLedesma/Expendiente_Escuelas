<?php

namespace App\Application\Archivo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\Escuela;
use App\Models\ResponsableNivel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Purga (borrado definitivo, solo admin) de una cuenta ya archivada. Para un solicitante
 * purga todos sus trámites archivados y los responsables archivados en el mismo lote, luego
 * la fila de solicitante y el usuario, todo en una transacción. Nunca purga una cuenta de
 * SEDEQ ni una referida por el historial de revisión (FK NOT NULL que la conservan); eso
 * cubre también la propia cuenta del admin y la del último admin.
 *
 * Orden de bloqueo: filas users (actor y objetivo, por id; luego las del lote), luego
 * escuela_niveles y escuelas por trámite (PurgarTramite), como ArchivarCuenta.
 */
class PurgarCuenta
{
    /** Historial de revisión que referencia a users: columna(s) por tabla. `revisores` lo cubre RolesSedeq::cuentaProtegida. */
    private const HISTORIAL = [
        'asignaciones_revision' => ['revisor_id', 'asignado_por'],
        'bitacora_asignaciones' => ['revisor_id', 'actor_id'],
        'decisiones_revision' => ['decidido_por'],
        'historial_decisiones_revision' => ['decidido_por'],
        'historial_estados_expediente' => ['usuario_sedeq_id'],
        'prioridades_revision' => ['actualizado_por'],
    ];

    public function __construct(
        private readonly PurgarTramite $purgarTramite,
        private readonly RegistrarAccion $registrar,
    ) {}

    /** @throws PrecondicionIncumplida|DatosInvalidos */
    public function ejecutar(int $actorId, int $userId, string $motivo, string $confirmacion): void
    {
        Purga::validar($motivo, $confirmacion);

        DB::transaction(function () use ($actorId, $userId, $motivo) {
            $filas = User::whereIn('id', [$userId, $actorId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $filas->get($actorId) ?? throw new PrecondicionIncumplida('rol', 'No tienes permiso para esta acción.');
            RolesSedeq::exigir($actor, RolesSedeq::ADMIN);
            $usuario = $filas->get($userId) ?? throw new PrecondicionIncumplida('cuenta', 'La cuenta no existe.');

            if ($usuario->id === $actor->id) {
                throw new PrecondicionIncumplida('cuenta', 'No puedes purgar tu propia cuenta.');
            }
            if ($usuario->archivado_at === null) {
                throw new PrecondicionIncumplida('archivo', 'Solo se puede purgar una cuenta archivada.');
            }
            $this->exigirPurgable($usuario);

            // Sin lote no hay cascada: ojo, where('archivado_lote', null) sería IS NULL y arrastraría a otras cuentas.
            $responsables = $usuario->archivado_lote === null ? new Collection : User::where('archivado_lote', $usuario->archivado_lote)
                ->whereNotNull('archivado_at')->whereKeyNot($userId)->orderBy('id')->lockForUpdate()->get();
            $responsables->each(fn (User $r) => $this->exigirPurgable($r));

            $tramites = 0;
            $solicitante = Solicitante::where('user_id', $userId)->lockForUpdate()->first();
            if ($solicitante !== null) {
                $escuelas = Escuela::withoutGlobalScope(EscuelaNoArchivada::class)
                    ->where('solicitante_id', $solicitante->id)->orderBy('id')->get(['id', 'archivado_at']);
                if ($escuelas->contains(fn (Escuela $e) => $e->archivado_at === null)) {
                    throw new PrecondicionIncumplida('archivo', 'La cuenta tiene trámites sin archivar; archívalos antes de purgarla.');
                }
                foreach ($escuelas as $escuela) {
                    $this->purgarTramite->purgar($actorId, $escuela->id, $motivo);
                    $tramites++;
                }
            }

            // Los accesos de los niveles purgados ya se fueron por cascade: cualquier acceso restante es en
            // otro solicitante (p. ej. reinvitado tras archivarse) y borrarlo sería en silencio. Se rechaza todo.
            if (ResponsableNivel::whereIn('user_id', $responsables->modelKeys())->exists()) {
                throw new PrecondicionIncumplida('cuenta', 'Un responsable de esta cuenta tiene acceso en otro trámite; no se puede purgar.');
            }

            $ids = [...$responsables->modelKeys(), $userId];
            $correos = [...$responsables->pluck('email'), $usuario->email];
            User::whereKey($responsables->modelKeys())->delete();
            $solicitante?->delete();
            $usuario->delete();
            // Datos personales fuera de FK: sesiones (IP, agente) y tokens de restablecimiento (correo).
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
            DB::table('password_reset_tokens')->whereIn('email', $correos)->delete();

            $this->registrar->ejecutar($actorId, RegistrarAccion::PURGAR_CUENTA, 'user', $userId, $motivo, [
                'tramites' => $tramites, 'responsables' => $responsables->count(),
            ]);
        });
    }

    /** @throws PrecondicionIncumplida */
    private function exigirPurgable(User $usuario): void
    {
        if (RolesSedeq::cuentaProtegida($usuario)) {
            throw new PrecondicionIncumplida('cuenta', 'Las cuentas de SEDEQ no se purgan.');
        }
        foreach (self::HISTORIAL as $tabla => $columnas) {
            if (DB::table($tabla)->whereAny($columnas, '=', $usuario->id)->exists()) {
                throw new PrecondicionIncumplida('cuenta', 'La cuenta está en el historial de revisión y no se puede purgar.');
            }
        }
    }
}
