<?php

namespace App\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EnviarTramite;
use App\Models\EscuelaNivel;
use App\Models\HistorialDecisionRevision;
use App\Models\ProgresoRevision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * El revisor (o el supervisor) acepta o rechaza un paso de un nivel en revisión.
 * Deja la decisión vigente, una fila de historial y el progreso; cuando todos los
 * pasos del nivel están decididos mueve el nivel a aprobado (ninguno rechazado) o
 * a con_observaciones. Un paso se puede volver a decidir mientras el nivel siga en
 * en_revision o con_observaciones, y ese cambio también puede mover el estado.
 *
 * Orden de bloqueo: solo escuela_niveles (la fila del nivel), como el resto del proyecto.
 * El estado y las decisiones se leen ya bajo ese bloqueo.
 */
class DecidirPaso
{
    public const ACEPTADO = 'aceptado';

    public const RECHAZADO = 'rechazado';

    private const APROBADO = 'aprobado';

    public function __construct(private readonly AccesoSedeq $acceso) {}

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
            if (! in_array($estadoClave, [EnviarTramite::EN_REVISION, 'con_observaciones'], true)) {
                throw new PrecondicionIncumplida(EnviarTramite::EN_REVISION, 'Solo se pueden revisar trámites en revisión.');
            }

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

            $estados = DB::table('decisiones_revision')->where('escuela_nivel_id', $escuelaNivelId)
                ->whereIn('paso_clave', $pasos)->pluck('estado');
            ProgresoRevision::updateOrCreate(
                ['escuela_nivel_id' => $escuelaNivelId],
                ['secciones_total' => count($pasos), 'secciones_revisadas' => $estados->count()],
            );

            if ($estados->count() === count($pasos)) {
                $destino = $estados->contains(self::RECHAZADO) ? 'con_observaciones' : self::APROBADO;
                if ($destino !== $estadoClave) {
                    $this->cambiarEstado($escuelaNivelId, $destino, $actorId);
                }
            }
        });
    }

    private function cambiarEstado(int $escuelaNivelId, string $destino, int $actorId): void
    {
        $estadoId = DB::table('estados_expediente')->where('clave', $destino)->value('id')
            ?? throw new \LogicException("Falta el estado {$destino} en estados_expediente.");
        DB::table('escuela_niveles')->where('id', $escuelaNivelId)->update(['estado_id' => $estadoId]);
        DB::table('historial_estados_expediente')->insert([
            'escuela_nivel_id' => $escuelaNivelId,
            'estado_id' => $estadoId,
            'comentario' => $destino === self::APROBADO ? 'Todos los pasos aceptados' : 'Pasos con observaciones',
            'usuario_sedeq_id' => $actorId,
            'fecha' => now(),
        ]);
    }
}
