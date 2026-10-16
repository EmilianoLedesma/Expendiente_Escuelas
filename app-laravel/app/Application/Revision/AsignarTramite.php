<?php

namespace App\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EnviarTramite;
use App\Models\AsignacionRevision;
use App\Models\BitacoraAsignacion;
use App\Models\EscuelaNivel;
use App\Models\ProgresoRevision;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El supervisor asigna niveles en revisión a un revisor, con tope por revisor
 * (config sedeq.revision.limite_por_revisor; cuenta solo niveles en_revision).
 * Con el tope alcanzado lanza LimiteDeRevisorAlcanzado; si el supervisor
 * confirma la sobrecarga, desasigna los niveles del revisor que haga falta, en
 * este orden: menos secciones revisadas, menor prioridad (baja < media < alta;
 * sin fila = media), asignado más recientemente. El progreso se conserva.
 *
 * Orden de bloqueo: la fila users del revisor (serializa el conteo contra el
 * tope entre supervisores simultáneos), luego escuela_niveles por id, luego
 * escuelas si alguna vez se tocan. Bloquear users ANTES que escuela_niveles es
 * una excepción deliberada al orden del proyecto: InvitarResponsableNivel y
 * RevocarResponsableNivel bloquean niveles y luego users, y EnviarTramite y
 * EliminarTramite nunca bloquean users. En la práctica no se cruzan, porque las
 * cuentas SEDEQ y las de solicitantes son distintas; si alguna vez se cruzaran,
 * Postgres detecta el deadlock y aborta una de las dos transacciones sin
 * corromper datos. El rol y el estado en_revision se comprueban ya bajo bloqueo.
 */
class AsignarTramite
{
    /** @throws PrecondicionIncumplida|LimiteDeRevisorAlcanzado (ver asignarSeleccion) */
    public function ejecutar(int $actorId, int $escuelaNivelId, int $revisorId, bool $confirmarSobrecarga = false): void
    {
        $this->asignarSeleccion($actorId, [$escuelaNivelId], $revisorId, $confirmarSobrecarga);
    }

    /**
     * Todo o nada: la selección completa cuenta contra el tope. Un nivel que el
     * revisor ya tiene no cuenta ni se registra.
     *
     * @param  array<int>  $escuelaNivelIds
     *
     * @throws PrecondicionIncumplida si el actor no es supervisor, el destino no es un revisor activo o un nivel no está en revisión.
     * @throws LimiteDeRevisorAlcanzado si se rebasa el tope sin confirmar, o si la selección completa sola ya lo rebasa.
     */
    public function asignarSeleccion(int $actorId, array $escuelaNivelIds, int $revisorId, bool $confirmarSobrecarga = false): void
    {
        DB::transaction(function () use ($actorId, $escuelaNivelIds, $revisorId, $confirmarSobrecarga) {
            RolesSedeq::exigir(User::findOrFail($actorId), RolesSedeq::SUPERVISOR);

            $revisor = User::whereKey($revisorId)->lockForUpdate()->first();
            if ($revisor === null || ! $revisor->hasRole(RolesSedeq::REVISOR)) {
                throw new PrecondicionIncumplida(RolesSedeq::REVISOR, 'El usuario elegido no es un revisor activo.');
            }

            $seleccion = collect($escuelaNivelIds)->map(fn ($id) => (int) $id)->unique()->values();
            $enRevision = EstadoRevision::idEnRevision();

            // También se bloquean los niveles que ya tiene el revisor (todos por id): de ahí
            // salen los desasignados por sobrecarga, y así nadie los mueve a la vez.
            $actuales = AsignacionRevision::where('revisor_id', $revisorId)->pluck('escuela_nivel_id');
            $estados = EscuelaNivel::whereIn('id', $seleccion->merge($actuales)->unique()->values())
                ->orderBy('id')->lockForUpdate()->pluck('estado_id', 'id');

            if ($enRevision === null || $seleccion->contains(fn (int $id) => (int) ($estados[$id] ?? 0) !== $enRevision)) {
                throw new PrecondicionIncumplida(EnviarTramite::EN_REVISION, 'Solo se pueden asignar trámites en revisión.');
            }

            $nuevos = $seleccion->diff(
                AsignacionRevision::where('revisor_id', $revisorId)->whereIn('escuela_nivel_id', $seleccion)->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)
            )->values();
            if ($nuevos->isEmpty()) {
                return;
            }

            $limite = (int) config('sedeq.revision.limite_por_revisor');
            $exceso = $this->enRevisionDe($revisorId, $enRevision)->count() + $nuevos->count() - $limite;
            // La selección completa (incluidos los que ya tiene) debe caber: la sobrecarga
            // solo puede desasignar niveles fuera de la selección.
            if ($exceso > 0 && (! $confirmarSobrecarga || $seleccion->count() > $limite)) {
                throw new LimiteDeRevisorAlcanzado($revisorId, $limite);
            }

            if ($exceso > 0) {
                $this->desasignarPorSobrecarga($actorId, $revisorId, $enRevision, $exceso, $seleccion);
            }

            $this->asignar($actorId, $revisorId, $nuevos);
        });
    }

    /** Asignaciones del revisor cuyo nivel sigue en revisión: lo que cuenta contra el tope. */
    private function enRevisionDe(int $revisorId, int $enRevision): Builder
    {
        return DB::table('asignaciones_revision as a')
            ->join('escuela_niveles as en', 'en.id', '=', 'a.escuela_nivel_id')
            ->where('a.revisor_id', $revisorId)
            ->where('en.estado_id', $enRevision);
    }

    /** @param Collection<int, int> $seleccion nunca se desasigna un nivel que se está asignando */
    private function desasignarPorSobrecarga(int $actorId, int $revisorId, int $enRevision, int $cuantos, Collection $seleccion): void
    {
        $rango = collect(EstadoRevision::PRIORIDADES)->map(fn (string $p, int $i) => "WHEN '{$p}' THEN {$i}")->implode(' ');

        $salen = $this->enRevisionDe($revisorId, $enRevision)
            ->whereNotIn('a.escuela_nivel_id', $seleccion)
            ->leftJoin('progreso_revision as p', 'p.escuela_nivel_id', '=', 'a.escuela_nivel_id')
            ->leftJoin('prioridades_revision as pr', 'pr.escuela_nivel_id', '=', 'a.escuela_nivel_id')
            ->orderByRaw('COALESCE(p.secciones_revisadas, 0)')
            ->orderByRaw("CASE COALESCE(pr.prioridad, '".EstadoRevision::PRIORIDAD_POR_DEFECTO."') {$rango} END")
            ->orderByDesc('a.asignado_at')
            ->orderByDesc('a.escuela_nivel_id')
            ->limit($cuantos)
            ->pluck('a.escuela_nivel_id');

        AsignacionRevision::whereIn('escuela_nivel_id', $salen)->delete();
        foreach ($salen as $id) {
            BitacoraAsignacion::create(['escuela_nivel_id' => $id, 'revisor_id' => $revisorId, 'accion' => 'desasignar_sobrecarga', 'actor_id' => $actorId]);
        }
    }

    /** @param Collection<int, int> $nuevos */
    private function asignar(int $actorId, int $revisorId, Collection $nuevos): void
    {
        $previos = AsignacionRevision::whereIn('escuela_nivel_id', $nuevos)->pluck('revisor_id', 'escuela_nivel_id');
        $ahora = now();

        foreach ($nuevos as $id) {
            AsignacionRevision::upsert(
                [['escuela_nivel_id' => $id, 'revisor_id' => $revisorId, 'asignado_por' => $actorId, 'asignado_at' => $ahora]],
                ['escuela_nivel_id'],
                ['revisor_id', 'asignado_por', 'asignado_at'],
            );
            ProgresoRevision::query()->insertOrIgnore(['escuela_nivel_id' => $id]);
            BitacoraAsignacion::create([
                'escuela_nivel_id' => $id,
                'revisor_id' => $revisorId,
                'accion' => $previos->has($id) ? 'reasignar' : 'asignar',
                'actor_id' => $actorId,
            ]);
        }
    }
}
