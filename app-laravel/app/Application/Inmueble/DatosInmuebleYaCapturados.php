<?php

namespace App\Application\Inmueble;

use App\Models\Plantel;

/**
 * La verificación "¿este plantel ya tiene sus datos de inmueble?", con dos
 * consumidores: si el formulario del sub-paso 1 se muestra o no, y si el
 * progreso de un segundo escuela_nivel del mismo plantel se auto-completa.
 *
 * metros_totales es el centinela porque es el único campo que este sub-paso
 * siempre exige.
 */
class DatosInmuebleYaCapturados
{
    public function ejecutar(int $plantelId): bool
    {
        return Plantel::where('id', $plantelId)->whereNotNull('metros_totales')->exists();
    }
}
