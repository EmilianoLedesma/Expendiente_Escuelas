<?php

namespace App\Application\Infraestructura;

use App\Models\InstalacionEspacio;
use App\Models\Sanitario;

/**
 * La verificación "¿este plantel ya tiene su infraestructura capturada?",
 * con dos consumidores: si el formulario del sub-paso 2 es editable o de
 * solo lectura (regla de reutilización de plantel), y si el progreso de un
 * segundo escuela_nivel se auto-completa. Una sola verificación, no dos
 * implementaciones que pueden divergir.
 *
 * Cubre las dos tablas ancladas a plantel_id (instalaciones_espacios y
 * sanitarios): todos los niveles_tipos_espacios se siembran con
 * obligatorio = false, así que un plantel puede tener sanitarios sin tener
 * ningún espacio declarado. Cualquiera de las dos, sola, ya significa que la
 * infraestructura del plantel se capturó.
 */
class InfraestructuraYaCapturada
{
    public function ejecutar(int $plantelId): bool
    {
        return InstalacionEspacio::where('plantel_id', $plantelId)->exists()
            || Sanitario::where('plantel_id', $plantelId)->exists();
    }

    /**
     * ADR-005: qué se capturó ya no se responde con un booleano — depende de
     * *qué* tipo_espacio, porque la aplicabilidad varía por nivel.
     *
     * @return list<int>
     */
    public function tiposCapturados(int $plantelId): array
    {
        return InstalacionEspacio::where('plantel_id', $plantelId)->pluck('tipo_espacio_id')->all();
    }

    /** @return list<string> */
    public function categoriasCapturadas(int $plantelId): array
    {
        return Sanitario::where('plantel_id', $plantelId)->pluck('categoria')->all();
    }
}
