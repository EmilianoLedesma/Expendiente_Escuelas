<?php

namespace App\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EnviarTramite;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\HistorialDecisionRevision;
use App\Models\ProgresoRevision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * El revisor (o el supervisor) acepta o rechaza un paso de un nivel en revisión.
 * Deja la decisión vigente, una fila de historial y el progreso. El cierre del nivel (aprobado o con_observaciones)
 * y de la ronda de devolución lo decide CierreRevisionNivel, que en media superior también espera las 20 secciones
 * del proyecto educativo (DecidirSeccionProyecto). Un paso se puede volver a decidir mientras el nivel siga en
 * en_revision. Un nivel con_observaciones está con el solicitante: no se decide hasta que reenvíe.
 *
 * Orden de bloqueo: escuela_niveles (la fila del nivel) y luego escuelas, como EnviarTramite.
 * El estado y las decisiones se leen ya bajo esos bloqueos.
 */
class DecidirPaso
{
    public const ACEPTADO = 'aceptado';

    public const RECHAZADO = 'rechazado';

    public function __construct(
        private readonly AccesoSedeq $acceso,
        private readonly CierreRevisionNivel $cierre,
    ) {}

    /**
     * @throws AuthorizationException si el actor no puede ver el nivel (incluye niveles en captura).
     * @throws PrecondicionIncumplida si el nivel no está en revisión ni con observaciones.
     * @throws DatosInvalidos si el paso no existe en el nivel, la decisión no es válida o falta el motivo del rechazo.
     */
    public function ejecutar(int $actorId, int $escuelaNivelId, string $pasoClave, string $decision, ?string $motivo = null): void
    {
        DB::transaction(function () use ($actorId, $escuelaNivelId, $pasoClave, $decision, $motivo) {
            $actor = User::findOrFail($actorId);

            // Solo se bloquea la fila del nivel; el catálogo se lee sin bloquear.
            $nivel = EscuelaNivel::whereKey($escuelaNivelId)->lockForUpdate()->first();
            // El acceso se comprueba bajo el bloqueo: una desasignación concurrente ya no puede colarse.
            if ($nivel === null || ! $this->acceso->puedeVerNivel($actor, $escuelaNivelId)) {
                throw new AuthorizationException('No puedes revisar este nivel.');
            }
            $estadoClave = (string) DB::table('estados_expediente')->where('id', $nivel->estado_id)->value('clave');
            $nivelClave = (string) DB::table('niveles_educativos')->where('id', $nivel->nivel_educativo_id)->value('clave');
            if ($estadoClave === 'con_observaciones') {
                throw new PrecondicionIncumplida(EnviarTramite::EN_REVISION, 'El trámite está con el solicitante; no se puede decidir hasta que lo reenvíe.');
            }
            if ($estadoClave !== EnviarTramite::EN_REVISION) {
                throw new PrecondicionIncumplida(EnviarTramite::EN_REVISION, 'Solo se pueden revisar trámites en revisión.');
            }
            // Orden niveles -> escuelas (como EnviarTramite): serializa los cierres por escuela, así el segundo
            // en decidir ya ve confirmado el estado del primero (READ COMMITTED).
            Escuela::whereKey($nivel->escuela_id)->lockForUpdate()->firstOrFail();

            $pasos = PasosRevision::paraNivel($nivelClave);
            if (! in_array($pasoClave, $pasos, true)) {
                throw new DatosInvalidos(['pasoClave' => 'Ese paso no aplica a este nivel.']);
            }
            if (! in_array($decision, [self::ACEPTADO, self::RECHAZADO], true)) {
                throw new DatosInvalidos(['pasoClave' => 'La decisión debe ser aceptado o rechazado.']);
            }
            $motivo = $decision === self::RECHAZADO ? trim((string) $motivo) : null;
            if ($decision === self::RECHAZADO && $motivo === '') {
                throw new DatosInvalidos(['motivo' => 'Escribe el motivo del rechazo.']);
            }

            $fila = [
                'escuela_nivel_id' => $escuelaNivelId,
                'paso_clave' => $pasoClave,
                'estado' => $decision,
                'motivo' => $motivo,
                'decidido_por' => $actorId,
                'decidido_at' => now(),
            ];
            // DecisionRevision tiene clave compuesta: se escribe con upsert, nunca con save().
            DB::table('decisiones_revision')->upsert([$fila], ['escuela_nivel_id', 'paso_clave'], ['estado', 'motivo', 'decidido_por', 'decidido_at']);
            HistorialDecisionRevision::create($fila);

            // El progreso es del revisor: solo cuenta sus pasos, nunca las secciones del proyecto educativo.
            ProgresoRevision::updateOrCreate(
                ['escuela_nivel_id' => $escuelaNivelId],
                ['secciones_total' => count($pasos), 'secciones_revisadas' => DB::table('decisiones_revision')->where('escuela_nivel_id', $escuelaNivelId)->whereIn('paso_clave', $pasos)->count()],
            );

            $this->cierre->tras($nivel, $nivelClave, $actorId);
        });
    }
}
