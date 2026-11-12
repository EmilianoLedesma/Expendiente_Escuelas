<?php

namespace App\Application\ProyectoEducativo;

use App\Models\ProyectoEducativoFormacionAccion;
use App\Models\ProyectoEducativoFormacionPolitica;

/**
 * Lectura común de los dos bloques de la sección 11 (Formación Docente): la política y las acciones guardadas de un tipo,
 * tal como las usa el formulario (horas "HH:MM", fechas "AAAA-MM-DD", duración como texto).
 */
abstract class FormacionProyectoCapturada
{
    /** @return array{politica: string|null, acciones: list<array<string, mixed>>} */
    protected function bloque(int $escuelaNivelId, string $tipo): array
    {
        $clave = ['escuela_nivel_id' => $escuelaNivelId, 'tipo' => $tipo];

        $acciones = [];
        foreach (ProyectoEducativoFormacionAccion::where($clave)->orderBy('orden')->get() as $fila) {
            $accion = [];
            foreach (ReglasFormacionProyecto::CAMPOS_ACCION as $campo) {
                $accion[$campo] = $fila->getAttribute($campo);
            }
            $accion['duracion_horas'] = (string) $fila->duracion_horas;
            $accion['hora_inicio'] = substr($fila->hora_inicio, 0, 5);
            $accion['hora_fin'] = substr($fila->hora_fin, 0, 5);
            $acciones[] = $accion;
        }

        return ['politica' => ProyectoEducativoFormacionPolitica::where($clave)->value('politica'), 'acciones' => $acciones];
    }
}
