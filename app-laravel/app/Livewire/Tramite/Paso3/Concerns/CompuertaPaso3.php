<?php

namespace App\Livewire\Tramite\Paso3\Concerns;

use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\EstadoPaso3;
use App\Models\EscuelaNivel;
use App\View\Components\Tramite\Progreso;

/**
 * Compuerta de entrada de cada página de Paso 3. Solo presentación: las
 * decisiones son de EstadoPaso2 (Paso 2 completo, WS-1.2) y EstadoPaso3
 * (orden de sub-pasos, WS-1.3); aquí únicamente se redirige. Se llama como
 * primera línea de mount(), antes de cualquier efecto (MarcarPasoCompletado):
 * un auto-completado en GET solo ocurre si la página ya es alcanzable.
 */
trait CompuertaPaso3
{
    private const RUTA_PROXIMOS_PASOS = 'tramite.paso3-proximos-pasos';

    /**
     * @param  string|null  $clave  clave de pasos_captura de esta página; null para
     *                              Paso3ProximosPasos, alcanzable cuando el primer pendiente ya no
     *                              tiene página construida (o no queda ninguno).
     * @return bool true si redirigió (el mount() que llama debe hacer return).
     */
    protected function redirigirSiNoAlcanzable(EscuelaNivel $escuelaNivel, ?string $clave): bool
    {
        if (! app(EstadoPaso2::class)->puedeSeleccionarNiveles($escuelaNivel->escuela_id)) {
            $this->redirectRoute('tramite.paso2', ['escuela' => $escuelaNivel->escuela_id]);

            return true;
        }

        $estadoPaso3 = app(EstadoPaso3::class);
        $destino = Progreso::RUTAS_PASO3[$estadoPaso3->primerPendiente($escuelaNivel->id) ?? ''] ?? self::RUTA_PROXIMOS_PASOS;

        $alcanzable = $clave === null
            ? $destino === self::RUTA_PROXIMOS_PASOS
            : $estadoPaso3->puedeAcceder($escuelaNivel->id, $clave);

        if ($alcanzable) {
            return false;
        }

        $this->redirectRoute($destino, ['escuelaNivel' => $escuelaNivel->id]);

        return true;
    }
}
