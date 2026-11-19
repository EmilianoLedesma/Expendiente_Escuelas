<?php

namespace App\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Revision\DTO\FilaExpediente;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Expediente del supervisor: un renglón por nivel de escuela activa en el estado pedido. Ofrece los estados que SEDEQ
 * ve (nunca el borrador del solicitante); los de las fases siguientes (visita, incorporado…) se suman aquí.
 */
final class ListarExpedientes
{
    /** @var array<string, string> clave de estado => etiqueta; el orden es el del filtro. */
    public const ESTADOS = [
        'aprobado' => 'Revisión documental aprobada',
        'en_revision' => 'En revisión',
        'con_observaciones' => 'Con observaciones',
        'rechazado' => 'Rechazado',
    ];

    /** @return list<FilaExpediente> */
    public function ejecutar(int $actorId, string $estado = 'aprobado', ?string $buscar = null): array
    {
        RolesSedeq::exigir(User::findOrFail($actorId), RolesSedeq::SUPERVISOR);
        if (! array_key_exists($estado, self::ESTADOS)) {
            throw new DatosInvalidos(['estado' => 'Ese estado no está disponible en el Expediente.']);
        }

        $estadoId = (int) DB::table('estados_expediente')->where('clave', $estado)->value('id');
        $nombre = "COALESCE(e.nombre_aprobado, (SELECT t.nombre_propuesto FROM ternas_nombres t WHERE t.escuela_id = e.id ORDER BY t.numero_propuesta LIMIT 1), 'Plantel sin nombre')";
        $buscar = trim((string) $buscar);
        $patron = '%'.addcslashes($buscar, '%_\\').'%';

        // ponytail: sin paginar; paginar si el Expediente pasa de unos cientos de niveles.
        return DB::table('escuela_niveles as en')
            ->join('escuelas as e', 'e.id', '=', 'en.escuela_id')
            ->join('niveles_educativos as ne', 'ne.id', '=', 'en.nivel_educativo_id')
            ->leftJoin('asignaciones_revision as a', 'a.escuela_nivel_id', '=', 'en.id')
            ->leftJoin('users as u', 'u.id', '=', 'a.revisor_id')
            ->where('en.estado_id', $estadoId)
            ->whereNull('e.archivado_at')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw("{$nombre} ILIKE ?", [$patron])->orWhere('ne.nombre', 'ilike', $patron)))
            ->selectRaw(
                "en.id, e.id as escuela_id, {$nombre} as escuela, ne.nombre as nivel, u.name as revisor_nombre,
                (SELECT MAX(h.fecha) FROM historial_estados_expediente h WHERE h.escuela_nivel_id = en.id AND h.estado_id = ?) as desde",
                [$estadoId]
            )
            ->orderByRaw('desde DESC NULLS LAST')
            ->orderByDesc('en.id')
            ->get()
            ->map(fn ($r) => new FilaExpediente(
                escuelaNivelId: (int) $r->id,
                escuelaId: (int) $r->escuela_id,
                escuela: $r->escuela,
                nivel: $r->nivel,
                desde: $r->desde === null ? null : Carbon::parse($r->desde),
                revisorNombre: $r->revisor_nombre,
            ))->all();
    }
}
