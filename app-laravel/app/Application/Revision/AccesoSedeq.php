<?php

namespace App\Application\Revision;

use App\Application\Tramite\TramiteEditable;
use App\Models\AsignacionRevision;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lectura SEDEQ (decisión pura; las Policies la combinan en `view`, nunca en una habilidad que escribe).
 * Nadie del lado SEDEQ ve un nivel en captura (borrador del solicitante). El supervisor ve todo lo demás;
 * el revisor, solo los niveles que tiene asignados y la escuela de esos niveles (datos a nivel escuela).
 */
final class AccesoSedeq
{
    public function puedeVerEscuela(User $usuario, int $escuelaId): bool
    {
        return $this->nivelesVisibles($usuario)?->where('escuela_id', $escuelaId)->exists() ?? false;
    }

    public function puedeVerNivel(User $usuario, int $escuelaNivelId): bool
    {
        return $this->nivelesVisibles($usuario)?->whereKey($escuelaNivelId)->exists() ?? false;
    }

    /**
     * Reporte PDF de la validación guardada al envío: cubre todos los niveles de la escuela, así que solo lo ve quien
     * ve todos (el supervisor, o un revisor asignado a cada nivel) y solo con la escuela entera fuera de captura.
     */
    public function puedeVerReporteEnviado(User $usuario, int $escuelaId): bool
    {
        $todos = EscuelaNivel::where('escuela_id', $escuelaId)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return $todos !== [] && $this->nivelesDeEscuela($usuario, $escuelaId) === $todos;
    }

    /** @return list<int> ids de los niveles de la escuela que el usuario puede ver (vacío sin acceso). */
    public function nivelesDeEscuela(User $usuario, int $escuelaId): array
    {
        return ($this->nivelesVisibles($usuario)?->where('escuela_id', $escuelaId)->orderBy('id')->pluck('id') ?? collect())
            ->map(fn ($id) => (int) $id)->values()->all();
    }

    /** @return Builder<EscuelaNivel>|null null = el usuario no tiene rol SEDEQ operativo. */
    private function nivelesVisibles(User $usuario): ?Builder
    {
        $consulta = EscuelaNivel::query()->whereNotIn(
            'estado_id',
            DB::table('estados_expediente')->where('clave', TramiteEditable::EN_CAPTURA)->select('id'),
        );

        return match (true) {
            $usuario->hasRole(RolesSedeq::SUPERVISOR) => $consulta,
            $usuario->hasRole(RolesSedeq::REVISOR) => $consulta->whereIn(
                'id',
                AsignacionRevision::where('revisor_id', $usuario->getKey())->select('escuela_nivel_id'),
            ),
            default => null,
        };
    }
}
