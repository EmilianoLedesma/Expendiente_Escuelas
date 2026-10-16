<?php

namespace App\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Models\BitacoraAsignacion;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Quita las asignaciones de un revisor al revocarlo (todosDe). Solo borra la
 * asignación: el progreso de revisión (progreso_revision) se conserva. Orden de
 * bloqueo el de AsignarTramite: fila users del revisor, luego escuela_niveles
 * por id. Es una excepción deliberada al orden del proyecto
 * (InvitarResponsableNivel y RevocarResponsableNivel bloquean niveles y luego
 * users; EnviarTramite y EliminarTramite nunca bloquean users), segura en la
 * práctica porque las cuentas SEDEQ y las de solicitantes son distintas; un
 * deadlock que Postgres detecte aborta una transacción sin corromper datos.
 */
class DesasignarTramite
{
    /**
     * Revocación: quita todas las asignaciones del revisor, registrando cada una.
     *
     * @return int cuántas se quitaron
     *
     * @throws PrecondicionIncumplida si el actor no es supervisor
     */
    public function todosDe(int $actorId, int $revisorId): int
    {
        return DB::transaction(function () use ($actorId, $revisorId) {
            RolesSedeq::exigir(User::findOrFail($actorId), RolesSedeq::SUPERVISOR);
            User::whereKey($revisorId)->lockForUpdate()->value('id');

            $ids = DB::table('asignaciones_revision')->where('revisor_id', $revisorId)->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)->all();
            EscuelaNivel::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id');

            return $this->quitar($actorId, $ids, 'desasignar_revocacion', $revisorId);
        });
    }

    /**
     * Borra con RETURNING para registrar exactamente lo que se quitó (y de quién).
     *
     * @param  list<int>  $escuelaNivelIds
     */
    private function quitar(int $actorId, array $escuelaNivelIds, string $accion, ?int $soloDeRevisor = null): int
    {
        if ($escuelaNivelIds === []) {
            return 0;
        }

        $marcas = implode(',', array_fill(0, count($escuelaNivelIds), '?'));
        $sql = "DELETE FROM asignaciones_revision WHERE escuela_nivel_id IN ({$marcas})";
        $params = $escuelaNivelIds;
        if ($soloDeRevisor !== null) {
            $sql .= ' AND revisor_id = ?';
            $params[] = $soloDeRevisor;
        }

        $quitadas = DB::select($sql.' RETURNING escuela_nivel_id, revisor_id', $params);
        foreach ($quitadas as $fila) {
            BitacoraAsignacion::create([
                'escuela_nivel_id' => $fila->escuela_nivel_id,
                'revisor_id' => $fila->revisor_id,
                'accion' => $accion,
                'actor_id' => $actorId,
            ]);
        }

        return count($quitadas);
    }
}
