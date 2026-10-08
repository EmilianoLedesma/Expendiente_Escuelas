<?php

namespace App\Livewire\Tramite\Concerns;

use App\Models\Escuela;
use Illuminate\Support\Facades\Gate;

/**
 * Paso 2 es solo del dueño. Quien captura un nivel sin ser dueño (un
 * responsable) no puede ir allí: si algo falta, vuelve al hub, que le dice qué.
 */
trait RedirigeAPaso2
{
    protected function redirigirAPaso2(int $escuelaId): void
    {
        $this->redirectRoute(
            Gate::allows('update', Escuela::findOrFail($escuelaId)) ? 'tramite.paso2' : 'tramite.resumen',
            ['escuela' => $escuelaId],
        );
    }
}
