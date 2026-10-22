<?php

namespace App\Application\Tramite;

use App\Application\Revision\PasosRevision;
use Illuminate\Support\Facades\DB;

/**
 * Qué pasos del trámite vuelven a ser editables tras una devolución. Solo hay pasos
 * reabiertos con la ronda cerrada (ningún nivel en revisión y al menos uno devuelto): un paso está
 * reabierto si un nivel en con_observaciones lo tiene rechazado. Un paso general
 * (plantel, responsable, niveles, documentos) se reabre si cualquier nivel lo rechazó;
 * uno del nivel, solo en el nivel que lo rechazó.
 */
final class PasosReabiertos
{
    public function rondaCerrada(int $escuelaId): bool
    {
        $claves = DB::table('escuela_niveles')
            ->join('estados_expediente', 'estados_expediente.id', '=', 'escuela_niveles.estado_id')
            ->where('escuela_niveles.escuela_id', $escuelaId)
            ->pluck('estados_expediente.clave');

        // Cerrada: ningún nivel en revisión y al menos uno devuelto.
        return ! $claves->contains(EnviarTramite::EN_REVISION) && $claves->contains('con_observaciones');
    }

    /** @param ?int $escuelaNivelId obligatorio para pasos del nivel; se ignora en los generales. */
    public function estaReabierto(int $escuelaId, string $pasoClave, ?int $escuelaNivelId = null): bool
    {
        foreach ($this->reabiertos($escuelaId) as $r) {
            if ($r['paso'] === $pasoClave && (in_array($pasoClave, PasosRevision::GENERALES, true) || $r['escuelaNivelId'] === $escuelaNivelId)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{paso: string, escuelaNivelId: int}> escuelaNivelId = el nivel que rechazó. */
    public function reabiertos(int $escuelaId): array
    {
        if (! $this->rondaCerrada($escuelaId)) {
            return [];
        }

        return DB::table('decisiones_revision')
            ->join('escuela_niveles', 'escuela_niveles.id', '=', 'decisiones_revision.escuela_nivel_id')
            ->join('estados_expediente', 'estados_expediente.id', '=', 'escuela_niveles.estado_id')
            ->where('escuela_niveles.escuela_id', $escuelaId)
            ->where('estados_expediente.clave', 'con_observaciones')
            ->where('decisiones_revision.estado', 'rechazado')
            ->orderBy('decisiones_revision.escuela_nivel_id')
            ->orderBy('decisiones_revision.paso_clave')
            ->get(['decisiones_revision.paso_clave', 'decisiones_revision.escuela_nivel_id'])
            ->map(fn ($f) => ['paso' => (string) $f->paso_clave, 'escuelaNivelId' => (int) $f->escuela_nivel_id])
            ->all();
    }
}
