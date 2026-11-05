<?php

namespace App\Application\Auditoria;

use App\Application\Revision\PasosRevision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Línea de tiempo de un trámite: junta lo que el sistema registró en cuatro tablas
 * (bitácora de acciones del admin, cambios de estado, decisiones de revisión y
 * asignaciones de revisor), más reciente primero. Solo lectura. Lo que el solicitante
 * captura no se registra en ninguna parte, así que no aparece.
 */
class ConsultarHistorialTramite
{
    private const ASIGNACIONES = [
        'asignar' => 'Asignó a',
        'reasignar' => 'Reasignó a',
        'desasignar_sobrecarga' => 'Desasignó por sobrecarga a',
        'desasignar_revocacion' => 'Desasignó por revocación a',
    ];

    /** @return list<array<string, mixed>> */
    public function ejecutar(int $escuelaId): array
    {
        $niveles = DB::table('escuela_niveles')
            ->join('niveles_educativos', 'niveles_educativos.id', '=', 'escuela_niveles.nivel_educativo_id')
            ->where('escuela_niveles.escuela_id', $escuelaId)
            ->pluck('niveles_educativos.nombre', 'escuela_niveles.id');
        $ids = $niveles->keys();

        $eventos = collect()
            ->concat($this->acciones($escuelaId))
            ->concat($this->estados($ids, $niveles))
            ->concat($this->decisiones($ids, $niveles))
            ->concat($this->asignaciones($ids, $niveles));

        return $eventos->reverse()->sortByDesc(fn (array $e) => $e['cuando']->getTimestamp())->values()->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function acciones(int $escuelaId): Collection
    {
        return DB::table('bitacora_acciones')
            ->leftJoin('users', 'users.id', '=', 'bitacora_acciones.actor_id')
            ->where('objeto_tipo', 'escuela')->where('objeto_id', $escuelaId)->orderBy('bitacora_acciones.id')
            ->get(['bitacora_acciones.accion', 'bitacora_acciones.motivo', 'bitacora_acciones.created_at', 'users.name'])
            ->map(fn ($f) => $this->evento($f->created_at, $f->name, EtiquetasBitacora::accion($f->accion), null, $f->motivo, 'accion'));
    }

    /**
     * @param  Collection<int, int>  $ids
     * @param  Collection<int, string>  $niveles
     * @return Collection<int, array<string, mixed>>
     */
    private function estados(Collection $ids, Collection $niveles): Collection
    {
        return DB::table('historial_estados_expediente')
            ->join('estados_expediente', 'estados_expediente.id', '=', 'historial_estados_expediente.estado_id')
            ->leftJoin('users', 'users.id', '=', 'historial_estados_expediente.usuario_sedeq_id')
            ->whereIn('escuela_nivel_id', $ids)->orderBy('historial_estados_expediente.id')
            ->get(['estados_expediente.nombre as estado', 'historial_estados_expediente.comentario', 'historial_estados_expediente.fecha', 'historial_estados_expediente.escuela_nivel_id', 'users.name'])
            ->map(fn ($f) => $this->evento($f->fecha, $f->name, 'Estado: '.$f->estado, $niveles[$f->escuela_nivel_id] ?? null, $f->comentario, 'estado'));
    }

    /**
     * @param  Collection<int, int>  $ids
     * @param  Collection<int, string>  $niveles
     * @return Collection<int, array<string, mixed>>
     */
    private function decisiones(Collection $ids, Collection $niveles): Collection
    {
        return DB::table('historial_decisiones_revision')
            ->leftJoin('users', 'users.id', '=', 'historial_decisiones_revision.decidido_por')
            ->whereIn('escuela_nivel_id', $ids)->orderBy('historial_decisiones_revision.id')
            ->get(['paso_clave', 'estado', 'motivo', 'decidido_at', 'escuela_nivel_id', 'users.name'])
            ->map(fn ($f) => $this->evento(
                $f->decidido_at, $f->name,
                ($f->estado === 'aceptado' ? 'Aceptó' : 'Rechazó').' el paso '.PasosRevision::titulo($f->paso_clave),
                $niveles[$f->escuela_nivel_id] ?? null, $f->motivo, $f->estado));
    }

    /**
     * @param  Collection<int, int>  $ids
     * @param  Collection<int, string>  $niveles
     * @return Collection<int, array<string, mixed>>
     */
    private function asignaciones(Collection $ids, Collection $niveles): Collection
    {
        return DB::table('bitacora_asignaciones')
            ->leftJoin('users as actor', 'actor.id', '=', 'bitacora_asignaciones.actor_id')
            ->leftJoin('users as revisor', 'revisor.id', '=', 'bitacora_asignaciones.revisor_id')
            ->whereIn('escuela_nivel_id', $ids)->orderBy('bitacora_asignaciones.id')
            ->get(['bitacora_asignaciones.accion', 'bitacora_asignaciones.created_at', 'bitacora_asignaciones.escuela_nivel_id', 'actor.name as actor', 'revisor.name as revisor'])
            ->map(fn ($f) => $this->evento(
                $f->created_at, $f->actor,
                trim((self::ASIGNACIONES[$f->accion] ?? $f->accion).' '.($f->revisor ?? '')),
                $niveles[$f->escuela_nivel_id] ?? null, null, 'asignacion'));
    }

    /** @return array<string, mixed> */
    private function evento(mixed $cuando, ?string $quien, string $que, ?string $nivel, ?string $detalle, string $tipo): array
    {
        return ['cuando' => Carbon::parse($cuando), 'quien' => $quien, 'que' => $que, 'nivel' => $nivel, 'detalle' => $detalle, 'tipo' => $tipo];
    }
}
