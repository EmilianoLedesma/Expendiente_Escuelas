<?php

namespace App\Application\Revision;

use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Application\Validaciones\UltimaValidacionFinal;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Aviso G6: pasos aceptados cuyo resultado del motor cambió desde la última evaluación guardada (la del envío o la
 * del cierre de la ronda; el reenvío no guarda una nueva, así que las correcciones del solicitante cuentan como cambio).
 * Una fila se compara por (paso, clave de regla) y solo se marca si antes era `cumple` y ahora no. Cubre tanto el efecto
 * entre pasos de la misma escuela como el envío de otra escuela del mismo plantel, porque ambos mueven el resultado en vivo.
 *
 * Solo lectura (nunca decide ni cambia estados) y informativo, como PistasDeRevision: si el motor falla o no hay
 * evaluación guardada devuelve [] (y reporta el fallo). Solo filas de la escuela y del nivel (PistasDeRevision::filasPorPaso).
 * Límite aceptado: los cambios que no mueven ninguna fila del motor no se detectan sin instantáneas.
 * Revalida AccesoSedeq.
 */
class ResultadosCambiados
{
    private const ESTADOS = [
        'cumple' => 'Cumple', 'advertencia' => 'Alerta', 'no_cumple' => 'No cumple',
        'no_evaluable' => 'No verificado', 'no_verificable' => 'No verificable por el sistema',
    ];

    public function __construct(
        private readonly AccesoSedeq $acceso,
        private readonly EjecutarValidacionFinal $validacion,
        private readonly UltimaValidacionFinal $ultimaValidacion,
    ) {}

    /**
     * @return array<string, list<string>> descripciones por clave de paso aceptado; vacío si nada cambió.
     *
     * @throws AuthorizationException
     */
    public function ejecutar(int $actorId, int $escuelaNivelId): array
    {
        if (! $this->acceso->puedeVerNivel(User::findOrFail($actorId), $escuelaNivelId)) {
            throw new AuthorizationException;
        }

        $aceptados = DB::table('decisiones_revision')->where('escuela_nivel_id', $escuelaNivelId)
            ->where('estado', DecidirPaso::ACEPTADO)->pluck('paso_clave')->all();
        if ($aceptados === []) {
            return [];
        }

        $escuelaId = EscuelaNivel::findOrFail($escuelaNivelId)->escuela_id;
        $guardada = $this->ultimaValidacion->paraEscuela($escuelaId);
        if ($guardada === null) {
            return [];
        }

        try {
            $ahora = $this->validacion->evaluar($escuelaId, exigirCompleto: false);
        } catch (\Throwable $e) {
            // Informativo: si el motor falla, la revisión sigue sin el aviso.
            report($e);

            return [];
        }

        $antes = PistasDeRevision::filasPorPaso($guardada, $escuelaNivelId);
        $cambios = [];

        foreach (PistasDeRevision::filasPorPaso($ahora, $escuelaNivelId) as $paso => $filas) {
            if (! in_array($paso, $aceptados, true)) {
                continue;
            }
            $previas = collect($antes[$paso] ?? [])->keyBy(fn (FilaValidacion $f) => $f->clave);
            foreach ($filas as $fila) {
                if ($fila->estado !== 'cumple' && $previas->get($fila->clave)?->estado === 'cumple') {
                    $cambios[$paso][] = rtrim(sprintf('%s: antes cumplía y ahora «%s». %s', $fila->titulo, self::ESTADOS[$fila->estado] ?? $fila->estado, $fila->mensaje));
                }
            }
        }

        return $cambios;
    }
}
