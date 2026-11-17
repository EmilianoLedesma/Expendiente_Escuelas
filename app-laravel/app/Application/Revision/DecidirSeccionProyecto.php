<?php

namespace App\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EnviarTramite;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\HistorialDecisionRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * El evaluador del proyecto educativo acepta o rechaza una de las 20 secciones de un nivel de media superior en
 * revisión. Misma mecánica que DecidirPaso (decisión vigente + historial, motivo obligatorio al rechazar, se puede
 * volver a decidir mientras el nivel siga en en_revision), con las claves pe_01 a pe_20. No toca el progreso del
 * revisor. El cierre del nivel lo decide CierreRevisionNivel, con las dos pistas.
 *
 * Orden de bloqueo: escuela_niveles (la fila del nivel) y luego escuelas, como DecidirPaso.
 */
class DecidirSeccionProyecto
{
    public function __construct(private readonly CierreRevisionNivel $cierre) {}

    /**
     * @throws PrecondicionIncumplida si el actor no es evaluador o el nivel no está en revisión.
     * @throws DatosInvalidos si la clave no es una sección, el nivel no es de media superior, la decisión no es válida o falta el motivo del rechazo.
     */
    public function ejecutar(int $actorId, int $escuelaNivelId, string $claveSeccion, string $decision, ?string $motivo = null): void
    {
        DB::transaction(function () use ($actorId, $escuelaNivelId, $claveSeccion, $decision, $motivo) {
            RolesSedeq::exigir(User::findOrFail($actorId), RolesSedeq::EVALUADOR_PROYECTO);

            $nivel = EscuelaNivel::whereKey($escuelaNivelId)->lockForUpdate()->firstOrFail();
            $estadoClave = (string) DB::table('estados_expediente')->where('id', $nivel->estado_id)->value('clave');
            $nivelClave = (string) DB::table('niveles_educativos')->where('id', $nivel->nivel_educativo_id)->value('clave');
            if ($estadoClave === 'con_observaciones') {
                throw new PrecondicionIncumplida(EnviarTramite::EN_REVISION, 'El trámite está con el solicitante; no se puede decidir hasta que lo reenvíe.');
            }
            if ($estadoClave !== EnviarTramite::EN_REVISION) {
                throw new PrecondicionIncumplida(EnviarTramite::EN_REVISION, 'Solo se pueden revisar trámites en revisión.');
            }
            Escuela::whereKey($nivel->escuela_id)->lockForUpdate()->firstOrFail();

            if (! SeccionesProyectoEducativo::aplicaA($nivelClave) || ! in_array($claveSeccion, SeccionesProyectoEducativo::clavesRevision(), true)) {
                throw new DatosInvalidos(['pasoClave' => 'Esa sección no aplica a este nivel.']);
            }
            if (! in_array($decision, [DecidirPaso::ACEPTADO, DecidirPaso::RECHAZADO], true)) {
                throw new DatosInvalidos(['pasoClave' => 'La decisión debe ser aceptado o rechazado.']);
            }
            $motivo = $decision === DecidirPaso::RECHAZADO ? trim((string) $motivo) : null;
            if ($decision === DecidirPaso::RECHAZADO && $motivo === '') {
                throw new DatosInvalidos(['motivo' => 'Escribe el motivo del rechazo.']);
            }

            $fila = [
                'escuela_nivel_id' => $escuelaNivelId,
                'paso_clave' => $claveSeccion,
                'estado' => $decision,
                'motivo' => $motivo,
                'decidido_por' => $actorId,
                'decidido_at' => now(),
            ];
            // DecisionRevision tiene clave compuesta: se escribe con upsert, nunca con save().
            DB::table('decisiones_revision')->upsert([$fila], ['escuela_nivel_id', 'paso_clave'], ['estado', 'motivo', 'decidido_por', 'decidido_at']);
            HistorialDecisionRevision::create($fila);

            $this->cierre->tras($nivel, $nivelClave, $actorId);
        });
    }
}
