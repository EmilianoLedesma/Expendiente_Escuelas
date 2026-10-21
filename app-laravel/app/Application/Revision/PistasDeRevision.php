<?php

namespace App\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Pistas del motor de validación por paso de revisión. Solo lectura (evaluar() no escribe) y solo
 * informativas: nunca bloquean ni deciden. Del reporte se toma lo de la escuela y lo del nivel
 * revisado; las secciones de los demás niveles se ignoran (filtrarían datos de otro nivel).
 * Revalida AccesoSedeq. Si el motor falla devuelve [] (y lo reporta): la revisión no depende de las pistas.
 */
final class PistasDeRevision
{
    public function __construct(
        private readonly AccesoSedeq $acceso,
        private readonly EjecutarValidacionFinal $validacion,
    ) {}

    /**
     * @return array<string, list<FilaValidacion>> filas por clave de paso; vacío si el trámite aún no está completo.
     *
     * @throws AuthorizationException
     */
    public function ejecutar(int $actorId, int $escuelaNivelId): array
    {
        if (! $this->acceso->puedeVerNivel(User::findOrFail($actorId), $escuelaNivelId)) {
            throw new AuthorizationException;
        }

        try {
            $reporte = $this->validacion->evaluar(EscuelaNivel::findOrFail($escuelaNivelId)->escuela_id);
        } catch (PrecondicionIncumplida) {
            return [];
        } catch (\Throwable $e) {
            // Las pistas son informativas: si el motor falla, la revisión sigue sin ellas.
            report($e);

            return [];
        }

        $pistas = ['documentos' => $reporte->filas];

        foreach ($reporte->niveles as $seccion) {
            if ($seccion->escuelaNivelId === $escuelaNivelId) {
                $pistas['documentos_nivel'] = $seccion->filas;
            }
        }

        foreach ($reporte->capacidad as $seccion) {
            if ($seccion->escuelaNivelId !== $escuelaNivelId) {
                continue;
            }
            foreach ($seccion->filas as $fila) {
                if ($fila->pasoCorreccion !== null) {
                    $pistas[$fila->pasoCorreccion][] = $fila;
                }
            }
        }

        return array_filter($pistas, fn (array $filas) => $filas !== []);
    }
}
