<?php

namespace App\Application\Revision;

use App\Application\ProyectoEducativo\EstadoSeccionesProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EnviarTramite;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Revisión del proyecto educativo de un nivel (media superior): las 20 secciones con su decisión vigente y su motivo.
 * Lo lee el evaluador (que decide) y el supervisor (solo lectura); el acceso lo decide AccesoSedeq::puedeVerProyecto.
 * `editable` solo es verdadero para el evaluador con el nivel en revisión.
 */
final class ConsultarRevisionProyecto
{
    public function __construct(
        private readonly AccesoSedeq $acceso,
        private readonly EstadoSeccionesProyecto $completas,
    ) {}

    /**
     * @return array{escuela: string, escuelaId: int, nivel: string, estado: string, editable: bool, revisadas: int, total: int, secciones: list<array{clave: string, slug: string, numero: int, titulo: string, estado: string, motivo: ?string, por: ?string, cuando: ?string, completa: bool}>}
     *
     * @throws AuthorizationException
     */
    public function ejecutar(int $actorId, int $escuelaNivelId): array
    {
        $actor = User::findOrFail($actorId);
        if (! $this->acceso->puedeVerProyecto($actor, $escuelaNivelId)) {
            throw new AuthorizationException('No puedes revisar este proyecto educativo.');
        }

        $nivel = EscuelaNivel::findOrFail($escuelaNivelId);
        $estado = (string) DB::table('estados_expediente')->where('id', $nivel->estado_id)->value('clave');
        $claves = SeccionesProyectoEducativo::clavesRevision();

        $decisiones = DB::table('decisiones_revision as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.decidido_por')
            ->where('d.escuela_nivel_id', $escuelaNivelId)->whereIn('d.paso_clave', $claves)
            ->get(['d.paso_clave', 'd.estado', 'd.motivo', 'd.decidido_at', 'u.name'])->keyBy('paso_clave');
        $completas = $this->completas->completas($escuelaNivelId);

        $secciones = [];
        foreach (SeccionesProyectoEducativo::todas() as $slug => $titulo) {
            $clave = SeccionesProyectoEducativo::claveRevision($slug);
            $d = $decisiones->get($clave);
            $secciones[] = [
                'clave' => $clave,
                'slug' => $slug,
                'numero' => count($secciones) + 1,
                'titulo' => $titulo,
                'estado' => $d === null ? 'pendiente' : (string) $d->estado,
                'motivo' => $d?->motivo,
                'por' => $d?->name,
                'cuando' => $d === null ? null : Carbon::parse($d->decidido_at)->format('d/m/Y H:i'),
                'completa' => $completas[$slug] ?? false,
            ];
        }

        return [
            'escuela' => (string) DB::table('escuelas as e')->where('e.id', $nivel->escuela_id)->selectRaw(
                "COALESCE(e.nombre_aprobado, (SELECT t.nombre_propuesto FROM ternas_nombres t WHERE t.escuela_id = e.id ORDER BY t.numero_propuesta LIMIT 1), 'Plantel sin nombre') as plantel"
            )->value('plantel'),
            'escuelaId' => (int) $nivel->escuela_id,
            'nivel' => (string) DB::table('niveles_educativos')->where('id', $nivel->nivel_educativo_id)->value('nombre'),
            'estado' => $estado,
            'editable' => $actor->hasRole(RolesSedeq::EVALUADOR_PROYECTO) && $estado === EnviarTramite::EN_REVISION,
            'revisadas' => $decisiones->count(),
            'total' => count($claves),
            'secciones' => $secciones,
        ];
    }
}
