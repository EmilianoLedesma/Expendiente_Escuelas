<?php

namespace App\Livewire\Tramite\Paso3\Concerns;

use App\Application\Tramite\EstadoPaso2;
use App\Models\EscuelaNivel;

/**
 * Compuerta de entrada de cada página de Paso 3 (WS-1.2). Solo presentación:
 * la decisión es de EstadoPaso2; aquí únicamente se redirige. Se llama como
 * primera línea de mount(), antes de cualquier efecto (MarcarPasoCompletado).
 */
trait RequierePaso2Completo
{
    /** @return bool true si redirigió (el mount() que llama debe hacer return). */
    protected function redirigirSiPaso2Incompleto(EscuelaNivel $escuelaNivel): bool
    {
        if (app(EstadoPaso2::class)->puedeSeleccionarNiveles($escuelaNivel->escuela_id)) {
            return false;
        }

        $this->redirectRoute('tramite.paso2', ['escuela' => $escuelaNivel->escuela_id]);

        return true;
    }
}
