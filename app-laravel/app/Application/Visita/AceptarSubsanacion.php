<?php

namespace App\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\User;
use App\Models\VisitaInspeccion;
use Illuminate\Support\Facades\DB;

/**
 * El supervisor acepta la subsanación: el nivel en subsanación pasa a visita_aprobada. La visita debe ser la última
 * del nivel, realizada con observaciones. Orden de bloqueo: escuela_nivel, escuela y visita.
 */
class AceptarSubsanacion
{
    /** @throws DatosInvalidos|PrecondicionIncumplida */
    public function ejecutar(User $actor, int $visitaId): void
    {
        RolesSedeq::exigir($actor, RolesSedeq::SUPERVISOR);

        $nivelId = (int) VisitaInspeccion::findOrFail($visitaId)->escuela_nivel_id;
        $escuelaId = (int) EscuelaNivel::findOrFail($nivelId)->escuela_id;

        DB::transaction(function () use ($actor, $visitaId, $nivelId, $escuelaId) {
            $nivel = EscuelaNivel::whereKey($nivelId)->lockForUpdate()->firstOrFail();
            Escuela::whereKey($escuelaId)->lockForUpdate()->firstOrFail();
            $visita = VisitaInspeccion::whereKey($visitaId)->lockForUpdate()->firstOrFail();

            $clave = DB::table('estados_expediente')->where('id', $nivel->estado_id)->value('clave');
            if ($clave !== 'en_subsanacion'
                || $visita->estado !== 'realizada'
                || $visita->resultado !== 'con_observaciones'
                || (int) VisitaInspeccion::where('escuela_nivel_id', $nivelId)->max('numero') !== (int) $visita->numero) {
                throw new DatosInvalidos(['visita' => 'Solo se puede aceptar la subsanación de la última visita de un nivel en subsanación.']);
            }

            $aprobada = (int) DB::table('estados_expediente')->where('clave', 'visita_aprobada')->value('id');
            $nivel->update(['estado_id' => $aprobada]);
            DB::table('historial_estados_expediente')->insert([
                'escuela_nivel_id' => $nivelId,
                'estado_id' => $aprobada,
                'comentario' => 'Subsanación aceptada',
                'usuario_sedeq_id' => $actor->id,
                'fecha' => now(),
            ]);
        });
    }
}
