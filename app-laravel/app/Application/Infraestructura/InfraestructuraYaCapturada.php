<?php

namespace App\Application\Infraestructura;

use App\Models\InstalacionEspacio;

/**
 * La verificación "¿este plantel ya tiene su infraestructura capturada?",
 * con dos consumidores: si el formulario del sub-paso 2 es editable o de
 * solo lectura (regla de reutilización de plantel), y si el progreso de un
 * segundo escuela_nivel se auto-completa. Una sola verificación, no dos
 * implementaciones que pueden divergir.
 */
class InfraestructuraYaCapturada
{
    public function ejecutar(int $plantelId): bool
    {
        return InstalacionEspacio::where('plantel_id', $plantelId)->exists();
    }
}
