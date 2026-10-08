<?php

namespace App\Livewire\Tramite\Paso3\Concerns;

use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\EstadoPaso24;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\ResumenTramite;
use App\Livewire\Tramite\Concerns\RedirigeAPaso2;
use App\Models\EscuelaNivel;

/**
 * Compuerta de entrada de cada página de Paso 3. Solo presentación: las
 * decisiones son de EstadoPaso2 (Paso 2 completo, WS-1.2), EstadoPaso24
 * (Paso 2.4 del nivel, WS-5b) y EstadoPaso3 (orden de sub-pasos, WS-1.3); aquí únicamente se redirige. Se llama como
 * primera línea de mount(), antes de cualquier efecto (MarcarPasoCompletado):
 * un auto-completado en GET solo ocurre si la página ya es alcanzable.
 */
trait CompuertaPaso3
{
    use RedirigeAPaso2;

    private const RUTA_PROXIMOS_PASOS = 'tramite.paso3-proximos-pasos';

    /**
     * @param  string  $clave  clave de pasos_captura de esta página.
     * @return bool true si redirigió (el mount() que llama debe hacer return).
     */
    protected function redirigirSiNoAlcanzable(EscuelaNivel $escuelaNivel, string $clave): bool
    {
        if (! app(EstadoPaso2::class)->puedeSeleccionarNiveles($escuelaNivel->escuela_id)) {
            $this->redirigirAPaso2($escuelaNivel->escuela_id);

            return true;
        }

        if (app(EstadoPaso24::class)->etapaFaltante($escuelaNivel->id) !== null) {
            $this->redirectRoute(ResumenTramite::RUTA_DOCUMENTOS_NIVEL, ['escuelaNivel' => $escuelaNivel->id]);

            return true;
        }

        $estadoPaso3 = app(EstadoPaso3::class);
        $destino = ResumenTramite::RUTAS_PASO3[$estadoPaso3->primerPendiente($escuelaNivel->id) ?? ''] ?? self::RUTA_PROXIMOS_PASOS;

        $alcanzable = $estadoPaso3->puedeAcceder($escuelaNivel->id, $clave);

        if ($alcanzable) {
            return false;
        }

        $this->redirectRoute($destino, ['escuelaNivel' => $escuelaNivel->id]);

        return true;
    }
}
