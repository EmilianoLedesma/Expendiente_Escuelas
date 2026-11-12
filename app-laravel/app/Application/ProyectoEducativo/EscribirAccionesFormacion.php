<?php

namespace App\Application\ProyectoEducativo;

use App\Models\ProyectoEducativoFormacionAccion;

/** Escritura de las acciones formativas de un nivel y un tipo ('inicial' o 'continua'); la usan los dos bloques de la sección 11. */
final class EscribirAccionesFormacion
{
    /**
     * Reemplaza las acciones del nivel y tipo por las recibidas (ya validadas y normalizadas), en ese orden. Debe correr
     * dentro de la transacción del guardado.
     *
     * @param  array{escuela_nivel_id: int, tipo: string}  $clave
     * @param  list<array<string, mixed>>  $acciones
     */
    public static function reemplazar(array $clave, array $acciones): void
    {
        ProyectoEducativoFormacionAccion::where($clave)->delete();
        foreach ($acciones as $i => $accion) {
            $accion['duracion_horas'] = (int) $accion['duracion_horas'];
            // Los días se guardan en el orden de la semana, no en el que se marcaron.
            $accion['dias'] = array_values(array_intersect(ReglasFormacionProyecto::DIAS, $accion['dias']));
            ProyectoEducativoFormacionAccion::create($clave + $accion + ['orden' => $i + 1]);
        }
    }
}
