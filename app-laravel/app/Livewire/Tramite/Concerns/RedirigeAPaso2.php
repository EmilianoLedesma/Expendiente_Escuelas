<?php

namespace App\Livewire\Tramite\Concerns;

use App\Application\Tramite\EstadoPaso2;
use App\Models\Escuela;
use Illuminate\Support\Facades\Gate;

/**
 * Paso 2 es solo del dueño. Al dueño se le manda a la primera etapa pendiente de Paso 2 (responsable → niveles →
 * documentos generales, EstadoPaso2::etapaFaltante), si puede escribir esa etapa (EscuelaPolicy::editarPaso). Quien captura un nivel sin ser dueño (un responsable) no
 * puede ir allí: si algo falta, vuelve al hub, que le dice qué.
 */
trait RedirigeAPaso2
{
    protected function redirigirAPaso2(int $escuelaId): void
    {
        // Las etapas de EstadoPaso2 son claves de paso: durante una devolución solo se manda a la etapa si está reabierta.
        $etapa = app(EstadoPaso2::class)->etapaFaltante($escuelaId) ?? EstadoPaso2::RESPONSABLE;
        if (! Gate::allows('editarPaso', [Escuela::findOrFail($escuelaId), $etapa])) {
            $this->redirectRoute('tramite.resumen', ['escuela' => $escuelaId]);

            return;
        }

        match ($etapa) {
            EstadoPaso2::NIVELES => $this->redirectRoute('tramite.paso2', ['escuela' => $escuelaId, 'seccion' => 'niveles']),
            EstadoPaso2::DOCUMENTOS => $this->redirectRoute('tramite.paso2-documentos', ['escuela' => $escuelaId]),
            default => $this->redirectRoute('tramite.paso2', ['escuela' => $escuelaId]),
        };
    }
}
