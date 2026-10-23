<?php

namespace Tests\Concerns;

use App\Models\Escuela;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Fixture de la devolución: niveles con decisiones vigentes de revisión, en con_observaciones. */
trait ConTramiteDevuelto
{
    use ConTramitesEnRevision;

    /**
     * Deja cada nivel mencionado en con_observaciones con sus decisiones vigentes.
     *
     * @param  array<int, array<string, string>>  $rechazos  [escuelaNivelId => [paso => motivo]]
     * @param  array<int, list<string>>  $aceptados  [escuelaNivelId => [paso, ...]]
     */
    protected function devolverTramite(Escuela $escuela, array $rechazos, array $aceptados = []): void
    {
        $revisor = User::factory()->create();
        $estadoId = DB::table('estados_expediente')->where('clave', 'con_observaciones')->value('id');
        $filas = [];
        foreach ($rechazos as $nivelId => $pasos) {
            foreach ($pasos as $paso => $motivo) {
                $filas[] = $this->filaDecision($nivelId, $paso, 'rechazado', $motivo, $revisor->id);
            }
        }
        foreach ($aceptados as $nivelId => $pasos) {
            foreach ($pasos as $paso) {
                $filas[] = $this->filaDecision($nivelId, $paso, 'aceptado', null, $revisor->id);
            }
        }
        // DecisionRevision tiene clave compuesta: se escribe con upsert, nunca con save().
        DB::table('decisiones_revision')->upsert($filas, ['escuela_nivel_id', 'paso_clave'], ['estado', 'motivo', 'decidido_por', 'decidido_at']);
        DB::table('escuela_niveles')->where('escuela_id', $escuela->id)
            ->whereIn('id', array_unique([...array_keys($rechazos), ...array_keys($aceptados)]))
            ->update(['estado_id' => $estadoId]);
    }

    /** @return array<string, mixed> */
    private function filaDecision(int $nivelId, string $paso, string $estado, ?string $motivo, int $revisorId): array
    {
        return ['escuela_nivel_id' => $nivelId, 'paso_clave' => $paso, 'estado' => $estado,
            'motivo' => $motivo, 'decidido_por' => $revisorId, 'decidido_at' => now()];
    }
}
