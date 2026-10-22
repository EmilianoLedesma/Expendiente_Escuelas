<?php

namespace App\Application\Tramite;

use App\Application\Revision\PasosRevision;
use App\Models\Escuela;
use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use Illuminate\Support\Facades\DB;

/**
 * Los motivos de rechazo que el solicitante ve tras una devolución. Solo existen con la ronda
 * cerrada (PasosReabiertos): con la ronda abierta un nivel puede estar devuelto por dentro y no se filtra nada.
 * El dueño ve todos los pasos rechazados; un responsable por nivel, solo los pasos de sus niveles
 * (nunca los generales: plantel, responsable, niveles, documentos).
 */
final class ObservacionesDeEscuela
{
    public function __construct(private readonly PasosReabiertos $pasosReabiertos) {}

    /** @return list<array{paso: string, titulo: string, nivel: ?string, escuelaNivelId: ?int, motivo: string}> */
    public function paraUsuario(int $escuelaId, int $usuarioId): array
    {
        $escuela = Escuela::findOrFail($escuelaId);

        if ((int) Solicitante::whereKey($escuela->solicitante_id)->value('user_id') === $usuarioId) {
            return $this->acotar($this->todas($escuelaId), null);
        }

        $propios = ResponsableNivel::where('user_id', $usuarioId)
            ->whereIn('escuela_nivel_id', $escuela->escuelaNiveles()->pluck('id'))
            ->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)->all();

        return $this->acotar($this->todas($escuelaId), $propios);
    }

    /**
     * Todos los pasos rechazados de la escuela (vista del dueño); vacío con la ronda abierta.
     *
     * @return list<array{paso: string, titulo: string, nivel: ?string, escuelaNivelId: ?int, motivo: string}>
     */
    public function todas(int $escuelaId): array
    {
        $reabiertos = $this->pasosReabiertos->reabiertos($escuelaId);
        if ($reabiertos === []) {
            return [];
        }

        $filas = DB::table('decisiones_revision')
            ->join('escuela_niveles', 'escuela_niveles.id', '=', 'decisiones_revision.escuela_nivel_id')
            ->join('niveles_educativos', 'niveles_educativos.id', '=', 'escuela_niveles.nivel_educativo_id')
            ->where('escuela_niveles.escuela_id', $escuelaId)
            ->where('decisiones_revision.estado', 'rechazado')
            ->get(['decisiones_revision.escuela_nivel_id', 'decisiones_revision.paso_clave', 'decisiones_revision.motivo', 'niveles_educativos.nombre'])
            ->keyBy(fn ($f) => $f->escuela_nivel_id.'|'.$f->paso_clave);

        $salida = [];
        foreach ($reabiertos as $r) {
            $fila = $filas->get($r['escuelaNivelId'].'|'.$r['paso']);
            $salida[] = [
                'paso' => $r['paso'],
                'titulo' => PasosRevision::titulo($r['paso']),
                'nivel' => $fila?->nombre,
                'escuelaNivelId' => $r['escuelaNivelId'],
                'motivo' => $fila === null ? '' : (string) $fila->motivo,
            ];
        }

        return $salida;
    }

    /**
     * @param  list<array{paso: string, titulo: string, nivel: ?string, escuelaNivelId: ?int, motivo: string}>  $todas
     * @param  list<int>|null  $soloNivelIds  null = vista completa (el dueño)
     * @return list<array{paso: string, titulo: string, nivel: ?string, escuelaNivelId: ?int, motivo: string}>
     */
    public function acotar(array $todas, ?array $soloNivelIds): array
    {
        if ($soloNivelIds === null) {
            return $todas;
        }

        return array_values(array_filter(
            $todas,
            fn (array $o) => ! in_array($o['paso'], PasosRevision::GENERALES, true) && in_array($o['escuelaNivelId'], $soloNivelIds, true),
        ));
    }
}
