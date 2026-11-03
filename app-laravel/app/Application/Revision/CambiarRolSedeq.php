<?php

namespace App\Application\Revision;

use App\Application\Archivo\RegistrarAccion;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Models\ResponsableNivel;
use App\Models\Revisor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * El admin cambia el rol de SEDEQ de una cuenta (o lo quita con null). Rechaza
 * cuentas con roles ajenos a SEDEQ o acceso de responsable. Salir de revisor equivale a revocarlo (revocado_at
 * y asignaciones de vuelta a la cola); volver a serlo limpia revocado_at.
 * Se rechaza: cambiarse a uno mismo, cuentas archivadas, de solicitante o responsable, el
 * mismo rol y bajar al último admin activo. Bloqueo: filas users (actor y
 * objetivo) por id, como ArchivarCuenta.
 */
class CambiarRolSedeq
{
    public function __construct(
        private readonly DesasignarTramite $desasignar,
        private readonly RegistrarAccion $registrar,
    ) {}

    /** @throws PrecondicionIncumplida|DatosInvalidos */
    public function ejecutar(int $actorId, int $userId, ?string $rol): void
    {
        if ($rol !== null && ! in_array($rol, RolesSedeq::TODOS, true)) {
            throw new DatosInvalidos(['rol' => 'El rol elegido no existe.']);
        }

        DB::transaction(function () use ($actorId, $userId, $rol) {
            $filas = User::whereIn('id', [$userId, $actorId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $filas->get($actorId) ?? throw new PrecondicionIncumplida('rol', 'No tienes permiso para esta acción.');
            RolesSedeq::exigir($actor, RolesSedeq::ADMIN);

            if ($actorId === $userId) {
                throw new PrecondicionIncumplida('cuenta', 'No puedes cambiar tu propio rol.');
            }
            $usuario = $filas->get($userId) ?? throw new PrecondicionIncumplida('cuenta', 'La cuenta no existe.');
            if ($usuario->archivado_at !== null) {
                throw new PrecondicionIncumplida('cuenta', 'La cuenta está archivada.');
            }
            if ($usuario->solicitante()->exists()) {
                throw new PrecondicionIncumplida('cuenta', 'Una cuenta de solicitante no puede tener rol de SEDEQ.');
            }
            // Como InvitarRevisor: nunca se apila un rol de SEDEQ sobre una cuenta de responsable u otro rol ajeno.
            if ($usuario->getRoleNames()->diff(RolesSedeq::TODOS)->isNotEmpty() || ResponsableNivel::where('user_id', $userId)->exists()) {
                throw new PrecondicionIncumplida('cuenta', 'Una cuenta de responsable de nivel no puede tener rol de SEDEQ.');
            }

            $actuales = $usuario->getRoleNames()->intersect(RolesSedeq::TODOS)->values()->all();
            if ($actuales === ($rol === null ? [] : [$rol])) {
                throw new PrecondicionIncumplida('cuenta', 'La cuenta ya tiene ese rol.');
            }
            if (in_array(RolesSedeq::ADMIN, $actuales, true) && $rol !== RolesSedeq::ADMIN
                && ! User::role(RolesSedeq::ADMIN)->whereNull('archivado_at')->whereKeyNot($userId)->exists()) {
                throw new PrecondicionIncumplida('cuenta', 'No se puede quitar el rol al último administrador activo.');
            }

            $eraRevisor = in_array(RolesSedeq::REVISOR, $actuales, true);
            foreach ($actuales as $actual) {
                $usuario->removeRole($actual);
            }
            if ($rol !== null) {
                $usuario->assignRole(Role::findOrCreate($rol, 'web'));
            }

            $liberadas = 0;
            if ($rol === RolesSedeq::REVISOR) {
                Revisor::updateOrCreate(['user_id' => $userId], ['revocado_at' => null]);
            } elseif ($eraRevisor) {
                Revisor::updateOrCreate(['user_id' => $userId], ['revocado_at' => now()]);
                $liberadas = $this->desasignar->liberar(
                    $actorId,
                    DB::table('asignaciones_revision')->where('revisor_id', $userId)->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)->all(),
                    'desasignar_revocacion',
                    $userId,
                );
            }

            $this->registrar->ejecutar($actorId, RegistrarAccion::CAMBIAR_ROL, 'user', $userId, null, [
                'de' => $actuales[0] ?? null, 'a' => $rol, 'asignaciones_liberadas' => $liberadas,
            ]);
        });
    }
}
