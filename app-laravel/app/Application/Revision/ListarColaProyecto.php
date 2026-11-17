<?php

namespace App\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cola del evaluador del proyecto educativo: los niveles de media superior en revisión, el que lleva más tiempo
 * esperando primero, con cuántas de las 20 secciones lleva decididas. Sin asignación (AccesoSedeq::nivelesConProyecto).
 * También la lee el supervisor, solo para supervisar.
 */
final class ListarColaProyecto
{
    public function __construct(private readonly AccesoSedeq $acceso) {}

    /**
     * @return list<array{escuelaNivelId: int, escuelaId: int, plantel: string, nivel: string, diasEspera: int, revisadas: int, total: int}>
     *
     * @throws PrecondicionIncumplida si el actor no es evaluador ni supervisor.
     */
    public function ejecutar(int $actorId): array
    {
        $actor = User::findOrFail($actorId);
        if (! $actor->hasAnyRole([RolesSedeq::EVALUADOR_PROYECTO, RolesSedeq::SUPERVISOR])) {
            throw new PrecondicionIncumplida('rol', 'No tienes permiso para esta acción.');
        }

        $ids = $this->acceso->nivelesConProyecto($actor);
        $enRevision = EstadoRevision::idEnRevision();
        $claves = SeccionesProyectoEducativo::clavesRevision();
        $revisadas = DB::table('decisiones_revision')->whereIn('escuela_nivel_id', $ids)->whereIn('paso_clave', $claves)
            ->groupBy('escuela_nivel_id')->selectRaw('escuela_nivel_id, COUNT(*) as n')->pluck('n', 'escuela_nivel_id');

        return DB::table('escuela_niveles as en')
            ->join('escuelas as e', 'e.id', '=', 'en.escuela_id')
            ->join('niveles_educativos as ne', 'ne.id', '=', 'en.nivel_educativo_id')
            ->whereIn('en.id', $ids)
            ->where('en.estado_id', $enRevision)
            ->whereNull('e.archivado_at')
            ->selectRaw(
                "en.id, e.id as escuela_id, ne.nombre as nivel,
                COALESCE(e.nombre_aprobado, (SELECT t.nombre_propuesto FROM ternas_nombres t WHERE t.escuela_id = e.id ORDER BY t.numero_propuesta LIMIT 1), 'Plantel sin nombre') as plantel,
                (SELECT MAX(h.fecha) FROM historial_estados_expediente h WHERE h.escuela_nivel_id = en.id AND h.estado_id = ?) as en_revision_desde",
                [$enRevision]
            )
            ->orderBy('en_revision_desde')->orderBy('en.id')
            ->get()
            ->map(fn ($r) => [
                'escuelaNivelId' => (int) $r->id,
                'escuelaId' => (int) $r->escuela_id,
                'plantel' => (string) $r->plantel,
                'nivel' => (string) $r->nivel,
                'diasEspera' => $r->en_revision_desde === null ? 0 : (int) max(0, Carbon::parse($r->en_revision_desde)->startOfDay()->diffInDays(now()->startOfDay())),
                'revisadas' => (int) ($revisadas[$r->id] ?? 0),
                'total' => count($claves),
            ])->all();
    }
}
