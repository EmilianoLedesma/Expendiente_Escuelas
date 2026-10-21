<?php

namespace App\Application\Revision;

use App\Application\Tramite\EnviarTramite;
use App\Models\EscuelaNivel;
use App\Models\TernaNombre;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Modelo de lectura de la revisión de un nivel: cada paso con su decisión vigente, su historial
 * y lo decidido sobre el mismo paso en otros niveles de la escuela (solo como pista).
 * Revalida AccesoSedeq. Las decisiones se leen con consultas planas (DecisionRevision tiene clave compuesta).
 */
final class ConsultarRevision
{
    public function __construct(private readonly AccesoSedeq $acceso) {}

    /**
     * @return array{
     *     escuelaNivelId: int, escuelaId: int, escuela: string|null, nivel: string, estado: string, editable: bool,
     *     revisadas: int, total: int,
     *     pasos: list<array{
     *         clave: string, titulo: string, estado: string, motivo: string|null, por: string|null, cuando: string|null,
     *         historial: list<array{estado: string, motivo: string|null, por: string, cuando: string}>,
     *         otrosNiveles: list<array{nivel: string, estado: string, por: string, cuando: string}>
     *     }>
     * }
     *
     * @throws AuthorizationException
     */
    public function ejecutar(int $actorId, int $escuelaNivelId): array
    {
        if (! $this->acceso->puedeVerNivel(User::findOrFail($actorId), $escuelaNivelId)) {
            throw new AuthorizationException;
        }

        $nivel = EscuelaNivel::with('nivelEducativo')->findOrFail($escuelaNivelId);
        $pasos = PasosRevision::paraNivel($nivel->nivelEducativo->clave);
        $estado = (string) DB::table('estados_expediente')->where('id', $nivel->estado_id)->value('clave');

        $actuales = DB::table('decisiones_revision')->where('escuela_nivel_id', $escuelaNivelId)->get()->keyBy('paso_clave');
        $historial = DB::table('historial_decisiones_revision')->where('escuela_nivel_id', $escuelaNivelId)
            ->orderBy('decidido_at')->orderBy('id')->get()->groupBy('paso_clave');
        $otros = DB::table('decisiones_revision as d')
            ->join('escuela_niveles as en', 'en.id', '=', 'd.escuela_nivel_id')
            ->join('niveles_educativos as ne', 'ne.id', '=', 'en.nivel_educativo_id')
            ->where('en.escuela_id', $nivel->escuela_id)->where('d.escuela_nivel_id', '!=', $escuelaNivelId)
            ->orderBy('en.id')
            ->get(['d.paso_clave', 'd.estado', 'd.decidido_por', 'd.decidido_at', 'ne.nombre as nivel'])->groupBy('paso_clave');

        $autores = User::whereIn('id', $actuales->pluck('decidido_por')
            ->merge($historial->flatten(1)->pluck('decidido_por'))
            ->merge($otros->flatten(1)->pluck('decidido_por'))->unique())->pluck('name', 'id');
        $por = fn (int|string $id): string => (string) ($autores[$id] ?? '');
        $fecha = fn (mixed $v): string => Carbon::parse($v)->format('d/m/Y H:i');

        return [
            'escuelaNivelId' => $nivel->id,
            'escuelaId' => $nivel->escuela_id,
            'escuela' => $nivel->escuela->nombre_aprobado
                ?? TernaNombre::where('escuela_id', $nivel->escuela_id)->orderBy('numero_propuesta')->value('nombre_propuesto'),
            'nivel' => $nivel->nivelEducativo->nombre,
            'estado' => $estado,
            'editable' => in_array($estado, [EnviarTramite::EN_REVISION, 'con_observaciones'], true),
            'revisadas' => $actuales->only($pasos)->count(),
            'total' => count($pasos),
            'pasos' => array_map(function (string $clave) use ($actuales, $historial, $otros, $por, $fecha) {
                $d = $actuales->all()[$clave] ?? null;

                return [
                    'clave' => $clave,
                    'titulo' => PasosRevision::titulo($clave),
                    'estado' => $d->estado ?? 'pendiente',
                    'motivo' => $d?->motivo,
                    'por' => $d ? $por($d->decidido_por) : null,
                    'cuando' => $d ? $fecha($d->decidido_at) : null,
                    'historial' => ($historial[$clave] ?? collect())->map(fn ($h) => [
                        'estado' => $h->estado, 'motivo' => $h->motivo, 'por' => $por($h->decidido_por), 'cuando' => $fecha($h->decidido_at),
                    ])->values()->all(),
                    'otrosNiveles' => ($otros[$clave] ?? collect())->map(fn ($o) => [
                        'nivel' => $o->nivel, 'estado' => $o->estado, 'por' => $por($o->decidido_por), 'cuando' => $fecha($o->decidido_at),
                    ])->values()->all(),
                ];
            }, $pasos),
        ];
    }
}
