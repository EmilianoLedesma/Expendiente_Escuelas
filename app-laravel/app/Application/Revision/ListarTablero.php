<?php

namespace App\Application\Revision;

use App\Application\Revision\DTO\BarraRevisor;
use App\Application\Revision\DTO\FilaTramite;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Tablero del supervisor: todos los niveles en revisión y la carga de cada revisor activo. */
final class ListarTablero
{
    /** @return array{filas: list<FilaTramite>, revisores: list<BarraRevisor>} */
    public function ejecutar(int $actorId): array
    {
        RolesSedeq::exigir(User::findOrFail($actorId), RolesSedeq::SUPERVISOR);

        return ['filas' => self::filas(null), 'revisores' => $this->barras()];
    }

    /**
     * Una sola consulta. Con $revisorId solo trae lo asignado a ese revisor.
     *
     * @return list<FilaTramite>
     */
    public static function filas(?int $revisorId): array
    {
        $rango = collect(EstadoRevision::PRIORIDADES)->reverse()->values()
            ->map(fn (string $p, int $i) => "WHEN '{$p}' THEN {$i}")->implode(' ');
        $porDefecto = EstadoRevision::PRIORIDAD_POR_DEFECTO;
        $enRevision = EstadoRevision::idEnRevision();

        return DB::table('escuela_niveles as en')
            ->join('escuelas as e', 'e.id', '=', 'en.escuela_id')
            ->join('niveles_educativos as ne', 'ne.id', '=', 'en.nivel_educativo_id')
            ->leftJoin('asignaciones_revision as a', 'a.escuela_nivel_id', '=', 'en.id')
            ->leftJoin('users as u', 'u.id', '=', 'a.revisor_id')
            ->leftJoin('prioridades_revision as pr', 'pr.escuela_nivel_id', '=', 'en.id')
            ->leftJoin('progreso_revision as p', 'p.escuela_nivel_id', '=', 'en.id')
            ->where('en.estado_id', $enRevision)
            ->when($revisorId !== null, fn ($q) => $q->where('a.revisor_id', $revisorId))
            ->selectRaw(
                "en.id, e.id as escuela_id, ne.nombre as nivel, u.id as revisor_id, u.name as revisor_nombre,
                COALESCE(e.nombre_aprobado, (SELECT t.nombre_propuesto FROM ternas_nombres t WHERE t.escuela_id = e.id ORDER BY t.numero_propuesta LIMIT 1), 'Plantel sin nombre') as plantel,
                COALESCE(pr.prioridad, '{$porDefecto}') as prioridad,
                COALESCE(p.secciones_revisadas, 0) as revisadas, COALESCE(p.secciones_total, 0) as total,
                (SELECT MAX(h.fecha) FROM historial_estados_expediente h WHERE h.escuela_nivel_id = en.id AND h.estado_id = ?) as en_revision_desde",
                [$enRevision]
            )
            ->orderByRaw("CASE COALESCE(pr.prioridad, '{$porDefecto}') {$rango} END")
            ->orderBy('en_revision_desde')
            ->orderBy('en.id')
            ->get()
            ->map(fn ($r) => new FilaTramite(
                escuelaNivelId: (int) $r->id,
                escuelaId: (int) $r->escuela_id,
                plantel: $r->plantel,
                nivel: $r->nivel,
                prioridad: $r->prioridad,
                diasEspera: $r->en_revision_desde === null ? 0 : (int) max(0, Carbon::parse($r->en_revision_desde)->startOfDay()->diffInDays(now()->startOfDay())),
                revisorId: $r->revisor_id === null ? null : (int) $r->revisor_id,
                revisorNombre: $r->revisor_nombre,
                revisadas: (int) $r->revisadas,
                total: (int) $r->total,
            ))->all();
    }

    /** @return list<BarraRevisor> */
    private function barras(): array
    {
        $limite = (int) config('sedeq.revision.limite_por_revisor');

        return DB::table('users as u')
            ->join('model_has_roles as mr', fn ($j) => $j->on('mr.model_id', '=', 'u.id')->where('mr.model_type', (new User)->getMorphClass()))
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->leftJoin('asignaciones_revision as a', 'a.revisor_id', '=', 'u.id')
            ->leftJoin('escuela_niveles as en', fn ($j) => $j->on('en.id', '=', 'a.escuela_nivel_id')->where('en.estado_id', EstadoRevision::idEnRevision()))
            ->leftJoin('progreso_revision as p', 'p.escuela_nivel_id', '=', 'en.id')
            ->where('r.name', RolesSedeq::REVISOR)
            ->groupBy('u.id', 'u.name')
            ->orderBy('u.name')->orderBy('u.id')
            ->selectRaw('u.id, u.name, COUNT(en.id) as asignados, COALESCE(SUM(p.secciones_revisadas), 0) as revisadas, COALESCE(SUM(p.secciones_total), 0) as total')
            ->get()
            ->map(fn ($r) => new BarraRevisor((int) $r->id, $r->name, (int) $r->asignados, $limite, (int) $r->revisadas, (int) $r->total))
            ->all();
    }
}
