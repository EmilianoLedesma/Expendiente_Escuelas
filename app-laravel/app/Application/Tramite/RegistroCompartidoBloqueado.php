<?php

namespace App\Application\Tramite;

use App\Models\Escuela;
use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\DB;

/**
 * Única definición de "los datos compartidos del plantel quedan bloqueados" (WS-7b-2):
 * otro escuela_nivel del plantel, de cualquier escuela (ADR-009), ya tiene datos propios
 * de Paso 3. Datos propios = filas en aulas_nivel, mobiliario_nivel, personal,
 * matricula_salas o matricula_grados, o modalidad no nula. Las banderas de
 * escuela_nivel_pasos no cuentan: no son datos.
 */
class RegistroCompartidoBloqueado
{
    /** Tablas hijas con escuela_nivel_id cuyas filas son datos propios del nivel. */
    private const TABLAS = ['aulas_nivel', 'mobiliario_nivel', 'personal', 'matricula_salas', 'matricula_grados'];

    public function bloqueado(int $plantelId, int $escuelaNivelId): bool
    {
        $otros = fn () => EscuelaNivel::query()
            ->whereIn('escuela_id', Escuela::where('plantel_id', $plantelId)->select('id'))
            ->where('id', '!=', $escuelaNivelId);

        if ($otros()->whereNotNull('modalidad')->exists()) {
            return true;
        }

        foreach (self::TABLAS as $tabla) {
            if (DB::table($tabla)->whereIn('escuela_nivel_id', $otros()->select('id'))->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * FOR UPDATE sobre todos los niveles del plantel, en orden de id. Va ANTES de
     * cualquier bloqueo de escuelas (orden del proyecto: escuela_niveles antes que
     * escuelas, ver TramiteEditable::asegurarEscuela). Debe llamarse dentro de una transacción.
     */
    public function bloquearNiveles(int $plantelId): void
    {
        EscuelaNivel::whereIn('escuela_id', Escuela::where('plantel_id', $plantelId)->select('id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');
    }
}
