<?php

namespace App\Application\Archivo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\DesasignarTramite;
use App\Application\Revision\RolesSedeq;
use App\Application\Tramite\TramiteEditable;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Archiva una cuenta (borrado lógico) con un solo lote para toda la cascada, para que
 * restaurar devuelva solo lo que esta acción archivó: la cuenta, los trámites aún no
 * archivados de su solicitante, los responsables por nivel de esos trámites que no
 * tienen otro acceso (ADR-015) y, si es revisor, sus asignaciones vuelven a la cola.
 *
 * Quien archiva su propia cuenta sigue las reglas de ADR-014 (ningún trámite enviado;
 * las cuentas de SEDEQ no se archivan solas). El admin archiva cualquier cuenta con
 * motivo, salvo la suya y la del último admin activo.
 *
 * Orden de bloqueo: filas users (actor y objetivo, por id), luego escuela_niveles y
 * escuelas por trámite (ArchivarTramite), como DesasignarTramite.
 */
class ArchivarCuenta
{
    public function __construct(
        private readonly ArchivarTramite $archivarTramite,
        private readonly DesasignarTramite $desasignar,
        private readonly RegistrarAccion $registrar,
    ) {}

    /** @throws PrecondicionIncumplida|DatosInvalidos */
    public function ejecutar(int $userId, int $actorId, ?string $motivo = null): void
    {
        DB::transaction(function () use ($userId, $actorId, $motivo) {
            // Ambas filas bloqueadas por id: dos admins que se archivan a la vez se serializan
            // y el segundo ve al primero ya archivado (no quedan cero admins activos).
            $filas = User::whereIn('id', [$userId, $actorId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $usuario = $filas->get($userId) ?? throw new PrecondicionIncumplida('cuenta', 'La cuenta no existe.');
            $actor = $filas->get($actorId) ?? throw new PrecondicionIncumplida('rol', 'No tienes permiso para esta acción.');
            $esAdmin = $actor->hasRole(RolesSedeq::ADMIN);

            $this->autorizar($usuario, $actor, $esAdmin, $motivo);

            $lote = (string) Str::uuid();
            $tramites = 0;
            $responsables = 0;
            $solicitante = Solicitante::where('user_id', $userId)->first();

            if ($solicitante !== null) {
                $escuelaIds = Escuela::where('solicitante_id', $solicitante->id)->orderBy('id')->pluck('id');
                // Antes de archivar: después el scope oculta esos niveles.
                $nivelIds = EscuelaNivel::whereIn('escuela_id', $escuelaIds)->pluck('id');

                try {
                    foreach ($escuelaIds as $escuelaId) {
                        $this->archivarTramite->ejecutar((int) $escuelaId, $actorId, $motivo, $lote);
                        $tramites++;
                    }
                } catch (PrecondicionIncumplida $e) {
                    if ($e->etapaFaltante !== TramiteEditable::EN_CAPTURA) {
                        throw $e;
                    }
                    throw new PrecondicionIncumplida(TramiteEditable::EN_CAPTURA, 'No puedes archivar tu cuenta: ya enviaste un trámite a SEDEQ.');
                }

                $responsables = $this->marcar(
                    User::whereIn('id', ResponsableNivel::whereIn('escuela_nivel_id', $nivelIds)->select('user_id'))
                        ->whereNotIn('id', ResponsableNivel::whereNotIn('escuela_nivel_id', $nivelIds)->select('user_id'))
                        ->whereNotIn('id', Solicitante::select('user_id'))
                        ->whereNull('archivado_at'),
                    $actorId, $motivo, $lote,
                );
            }

            $this->marcar(User::whereKey($userId), $actorId, $motivo, $lote);

            $liberadas = $this->desasignar->liberar(
                $actorId,
                DB::table('asignaciones_revision')->where('revisor_id', $userId)->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)->all(),
                'desasignar_archivo',
                $userId,
            );

            $this->registrar->ejecutar($actorId, RegistrarAccion::ARCHIVAR_CUENTA, 'user', $userId, $motivo, [
                'lote' => $lote, 'tramites' => $tramites, 'responsables' => $responsables, 'asignaciones_liberadas' => $liberadas,
            ]);
        });
    }

    /** @throws PrecondicionIncumplida|DatosInvalidos */
    private function autorizar(User $usuario, User $actor, bool $esAdmin, ?string $motivo): void
    {
        if ($usuario->archivado_at !== null) {
            throw new PrecondicionIncumplida('archivo', 'La cuenta ya está archivada.');
        }

        if (! $esAdmin) {
            if ($actor->id !== $usuario->id) {
                throw new PrecondicionIncumplida('rol', 'No tienes permiso para esta acción.');
            }
            if (RolesSedeq::cuentaProtegida($usuario)) {
                throw new PrecondicionIncumplida('cuenta', 'Las cuentas de SEDEQ no se pueden archivar desde el perfil.');
            }

            return;
        }

        if ($actor->id === $usuario->id) {
            throw new PrecondicionIncumplida('cuenta', 'No puedes archivar tu propia cuenta.');
        }
        if (trim((string) $motivo) === '') {
            throw new DatosInvalidos(['motivo' => 'Escribe el motivo para archivar la cuenta.']);
        }
        if ($usuario->hasRole(RolesSedeq::ADMIN)
            && ! User::role(RolesSedeq::ADMIN)->whereNull('archivado_at')->whereKeyNot($usuario->id)->exists()) {
            throw new PrecondicionIncumplida('cuenta', 'No se puede archivar al último administrador activo.');
        }
    }

    /** @param Builder<User> $usuarios */
    private function marcar(Builder $usuarios, int $actorId, ?string $motivo, string $lote): int
    {
        return $usuarios->update([
            'archivado_at' => now(),
            'archivado_por' => $actorId,
            'archivado_motivo' => $motivo,
            'archivado_lote' => $lote,
        ]);
    }
}
