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
    /**
     * Motivo de `documentos_nivel` reabierto por un responsable reabierto: no lo escribió el revisor. Declarativo, porque
     * lo lee también el responsable del nivel, que no puede corregir el responsable legal.
     */
    public const MOTIVO_FORMATO = 'Se reabrió porque el responsable legal está en corrección: si cambian sus datos que imprime el Formato de solicitud firmado de este nivel, el Formato se descarta y hay que volver a cargarlo.';

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

        $nombres = DB::table('escuela_niveles')
            ->join('niveles_educativos', 'niveles_educativos.id', '=', 'escuela_niveles.nivel_educativo_id')
            ->where('escuela_niveles.escuela_id', $escuelaId)
            ->pluck('niveles_educativos.nombre', 'escuela_niveles.id');

        $salida = [];
        foreach ($reabiertos as $r) {
            $fila = $filas->get($r['escuelaNivelId'].'|'.$r['paso']);
            $salida[] = [
                'paso' => $r['paso'],
                'titulo' => PasosRevision::titulo($r['paso']),
                'nivel' => $nombres->get($r['escuelaNivelId']),
                'escuelaNivelId' => $r['escuelaNivelId'],
                // Sin fila: documentos_nivel derivado de un responsable reabierto (PasosReabiertos::reabiertos).
                'motivo' => $fila === null ? self::MOTIVO_FORMATO : (string) $fila->motivo,
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
