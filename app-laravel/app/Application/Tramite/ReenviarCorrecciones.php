<?php

namespace App\Application\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\DecidirPaso;
use App\Application\Revision\PasosRevision;
use App\Application\Validaciones\DTO\SeccionCapacidad;
use App\Application\Validaciones\DTO\SeccionNivel;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\ProgresoRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Devolución (spec 5.5): el dueño reenvía las correcciones de una ronda cerrada. Cada nivel
 * con_observaciones vuelve a en_revision sin sus decisiones rechazadas (las aceptadas se quedan) y con
 * su progreso recalculado; los niveles aprobados no se tocan. No toca asignaciones_revision ni
 * historial_decisiones_revision, ni guarda evaluación: la línea base del G6 es la del cierre de la ronda.
 * La propiedad la decide EscuelaPolicy::reenviar (solo el dueño) antes de llegar aquí.
 *
 * Orden de bloqueo: todos los escuela_niveles por id y luego la escuela, como EnviarTramite y DecidirPaso;
 * una decisión del revisor que llegue a la vez espera el bloqueo de su nivel y relee el estado.
 */
class ReenviarCorrecciones
{
    private const CON_OBSERVACIONES = 'con_observaciones';

    /** Estados de sección del hub que cuentan como completos (los mismos que ResumenTramiteDTO::completo). */
    private const COMPLETOS = ['completado', 'no_disponible', 'no_aplica'];

    public function __construct(
        private readonly PasosReabiertos $pasosReabiertos,
        private readonly ResumenTramite $resumenTramite,
        private readonly EjecutarValidacionFinal $validacionFinal,
    ) {}

    /** @throws PrecondicionIncumplida si la ronda no está cerrada, falta completar un paso reabierto o algo bloquea. */
    public function ejecutar(int $escuelaId, int $userId): void
    {
        DB::transaction(function () use ($escuelaId, $userId) {
            $leerNiveles = fn () => EscuelaNivel::where('escuela_id', $escuelaId)->orderBy('id')->lockForUpdate()->pluck('estado_id', 'id');
            $leerNiveles();
            Escuela::whereKey($escuelaId)->lockForUpdate()->firstOrFail();
            // Relectura con la escuela ya bloqueada, como EnviarTramite.
            $leerNiveles();

            if (! $this->pasosReabiertos->rondaCerrada($escuelaId)) {
                throw new PrecondicionIncumplida(EnviarTramite::EN_REVISION, 'El trámite no tiene observaciones por corregir.');
            }

            $conObservaciones = (int) DB::table('estados_expediente')->where('clave', self::CON_OBSERVACIONES)->value('id');
            $afectados = EscuelaNivel::with('nivelEducativo')->where('escuela_id', $escuelaId)
                ->where('estado_id', $conObservaciones)->orderBy('id')->get();

            $this->asegurarListo($escuelaId, $afectados->modelKeys());

            $enRevision = (int) DB::table('estados_expediente')->where('clave', EnviarTramite::EN_REVISION)->value('id');
            $reabiertos = $this->pasosReabiertos->reabiertos($escuelaId);
            foreach ($afectados as $nivel) {
                // Las rechazadas, y la decisión de un paso reabierto sin rechazo (documentos_nivel derivado de un
                // responsable reabierto): su Formato pudo cambiar, el revisor vuelve a decidirlo.
                $pasosDelNivel = array_column(array_filter($reabiertos, fn (array $r) => $r['escuelaNivelId'] === $nivel->id), 'paso');
                DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)
                    ->where(fn ($q) => $q->where('estado', DecidirPaso::RECHAZADO)->orWhereIn('paso_clave', $pasosDelNivel))
                    ->delete();
                $pasos = PasosRevision::paraNivel($nivel->nivelEducativo->clave);
                ProgresoRevision::updateOrCreate(
                    ['escuela_nivel_id' => $nivel->id],
                    ['secciones_total' => count($pasos), 'secciones_revisadas' => DB::table('decisiones_revision')
                        ->where('escuela_nivel_id', $nivel->id)->whereIn('paso_clave', $pasos)->count()],
                );
            }
            EscuelaNivel::whereIn('id', $afectados->modelKeys())->update(['estado_id' => $enRevision]);
            DB::table('historial_estados_expediente')->insert($afectados->map(fn (EscuelaNivel $nivel) => [
                'escuela_nivel_id' => $nivel->id,
                'estado_id' => $enRevision,
                'comentario' => 'Correcciones reenviadas por el solicitante',
                // NOT NULL REFERENCES users(id): aquí es el users.id del solicitante, como en EnviarTramite.
                'usuario_sedeq_id' => $userId,
                'fecha' => now(),
            ])->all());

            DB::afterCommit(fn () => Log::info('Correcciones reenviadas a SEDEQ', [
                'escuela_id' => $escuelaId,
                'niveles' => $afectados->count(),
                'usuario_id' => $userId,
            ]));
        });
    }

    /**
     * (a) Cada paso reabierto vuelve a estar completo según las compuertas de captura (sin el congelamiento de
     * «enviado»). (b) La regla de envío sin nada que bloquee entre las filas de los niveles devueltos y, solo si el
     * paso `documentos` se reabrió, las documentales de la escuela: un hermano aprobado nunca bloquea, ni una fila
     * de la escuela que el solicitante no puede corregir en esta ronda.
     *
     * @param  list<int>  $afectados  ids de los escuela_niveles con_observaciones
     */
    private function asegurarListo(int $escuelaId, array $afectados): void
    {
        $resumen = $this->resumenTramite->sinCongelar($escuelaId);
        $estados = [];
        foreach ($resumen->generales as $s) {
            $estados["g:{$s->clave}"] = $s->estado;
        }
        foreach ($resumen->niveles as $n) {
            foreach ($n->secciones as $s) {
                $estados["n:{$n->escuelaNivelId}:{$s->clave}"] = $s->estado;
            }
        }

        $faltan = [];
        $reabiertos = $this->pasosReabiertos->reabiertos($escuelaId);
        foreach ($reabiertos as $r) {
            $clave = in_array($r['paso'], PasosRevision::GENERALES, true) ? "g:{$r['paso']}" : "n:{$r['escuelaNivelId']}:{$r['paso']}";
            if (! in_array($estados[$clave] ?? null, self::COMPLETOS, true)) {
                $faltan[PasosRevision::titulo($r['paso'])] = true;
            }
        }
        if ($faltan !== []) {
            throw new PrecondicionIncumplida(EjecutarValidacionFinal::ETAPA, 'Completa los pasos con observaciones antes de reenviar: '.implode(', ', array_keys($faltan)).'.');
        }

        $evaluada = $this->validacionFinal->evaluar($escuelaId, exigirCompleto: false);
        // Las filas documentales de la escuela solo bloquean si `documentos` se reabrió: si no, el solicitante no
        // podría corregirlas (un cambio de responsable o niveles solo levanta la marca G6 para el revisor, spec decisión 6).
        $documentosReabierto = in_array('documentos', array_column($reabiertos, 'paso'), true);
        $motivos = ValidacionFinal::bloqueantes(
            $documentosReabierto ? $evaluada->filas : [],
            array_values(array_filter($evaluada->capacidad, fn (SeccionCapacidad $s) => in_array($s->escuelaNivelId, $afectados, true))),
            array_values(array_filter($evaluada->niveles, fn (SeccionNivel $s) => in_array($s->escuelaNivelId, $afectados, true))),
        );
        if ($motivos !== []) {
            throw new PrecondicionIncumplida(EjecutarValidacionFinal::ETAPA, 'Aún no puedes reenviar. Corrige: '.implode('; ', $motivos).'.');
        }
    }
}
