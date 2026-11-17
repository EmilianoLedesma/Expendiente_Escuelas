<?php

namespace App\Application\Revision;

use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\PasosReabiertos;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Models\EscuelaNivel;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cierre de la revisión de un nivel, común a las dos pistas: los pasos del revisor (DecidirPaso) y, en media superior,
 * las 20 secciones del proyecto educativo del evaluador (DecidirSeccionProyecto). El nivel solo se mueve cuando TODAS
 * las decisiones de las dos pistas están tomadas: aprobado si ninguna se rechazó, con_observaciones si alguna sí.
 * Después, igual que antes, cierra la ronda de devolución o avisa la aprobación total de la escuela.
 *
 * Se llama dentro de la transacción de quien decide, con el nivel y la escuela ya bloqueados (orden escuela_niveles ->
 * escuelas, como EnviarTramite) y la decisión ya escrita.
 */
class CierreRevisionNivel
{
    private const APROBADO = 'aprobado';

    public function __construct(
        private readonly PasosReabiertos $pasosReabiertos,
        private readonly EjecutarValidacionFinal $validacionFinal,
    ) {}

    public function tras(EscuelaNivel $nivel, string $nivelClave, int $actorId): void
    {
        $pasos = PasosRevision::paraNivel($nivelClave);
        $claves = SeccionesProyectoEducativo::aplicaA($nivelClave) ? [...$pasos, ...SeccionesProyectoEducativo::clavesRevision()] : $pasos;
        $estados = DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)->whereIn('paso_clave', $claves)->pluck('estado');

        if ($estados->count() === count($claves)) {
            $this->cambiarEstado($nivel->id, $estados->contains(DecidirPaso::RECHAZADO) ? 'con_observaciones' : self::APROBADO, $actorId);
        }

        // Una decisión cierra la ronda sii rondaCerrada es true tras escribir el nuevo estado (el nivel venía en revisión).
        if ($this->pasosReabiertos->rondaCerrada($nivel->escuela_id)) {
            $this->cerrarRonda($nivel->escuela_id);
        } elseif ($this->todosAprobados($nivel->escuela_id)) {
            $escuelaId = $nivel->escuela_id;
            DB::afterCommit(fn () => event(new RevisionAprobada($escuelaId)));
        }
    }

    /** Revisión terminada sin observaciones: todos los niveles aprobados (ninguno en revisión ni devuelto). */
    private function todosAprobados(int $escuelaId): bool
    {
        $claves = DB::table('escuela_niveles')
            ->join('estados_expediente', 'estados_expediente.id', '=', 'escuela_niveles.estado_id')
            ->where('escuela_niveles.escuela_id', $escuelaId)
            ->pluck('estados_expediente.clave');

        return $claves->isNotEmpty() && $claves->every(fn ($clave) => $clave === self::APROBADO);
    }

    /** La línea base del G6 nunca bloquea ni revierte la decisión del revisor. */
    private function cerrarRonda(int $escuelaId): void
    {
        try {
            // Punto de guardado: un error de BD aquí no debe envenenar la transacción de la decisión.
            DB::transaction(fn () => $this->validacionFinal->guardar($escuelaId, $this->validacionFinal->evaluar($escuelaId, exigirCompleto: false)));
        } catch (DeadlockException $e) {
            // Un deadlock aborta la transacción de PG: tragarlo convertiría el COMMIT en ROLLBACK silencioso.
            throw $e;
        } catch (Throwable $e) {
            report($e);
        }
        DB::afterCommit(fn () => event(new RondaCerrada($escuelaId)));
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
