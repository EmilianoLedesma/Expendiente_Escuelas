<?php

namespace App\Application\Tramite;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Única fuente de verdad para el orden de los sub-pasos de Paso 3 (WS-1.3):
 * el sub-paso N es alcanzable si y solo si el N-1 está `completado` en
 * escuela_nivel_pasos. El orden sale de pasos_captura.orden — no hay aquí
 * una segunda copia de la secuencia, así que añadir un sub-paso (WS-8) es
 * solo añadirlo al catálogo. Los componentes solo leen el resultado para
 * decidir a dónde redirigir (ADR-001).
 */
class EstadoPaso3
{
    /** Clave del primer sub-paso sin completar, o null si todos lo están. */
    public function primerPendiente(int $escuelaNivelId): ?string
    {
        $completados = $this->completados($escuelaNivelId);

        foreach ($this->catalogo() as $clave) {
            if (! in_array($clave, $completados, true)) {
                return $clave;
            }
        }

        return null;
    }

    public function puedeAcceder(int $escuelaNivelId, string $clave): bool
    {
        $catalogo = $this->catalogo();
        $posicion = array_search($clave, $catalogo, true);

        if ($posicion === false) {
            throw new InvalidArgumentException("paso_captura desconocido: {$clave}");
        }

        return $posicion === 0 || in_array($catalogo[$posicion - 1], $this->completados($escuelaNivelId), true);
    }

    /** @return list<string> */
    private function catalogo(): array
    {
        return DB::table('pasos_captura')->orderBy('orden')->pluck('clave')->all();
    }

    /** @return list<string> */
    private function completados(int $escuelaNivelId): array
    {
        return DB::table('escuela_nivel_pasos')
            ->join('pasos_captura', 'pasos_captura.id', '=', 'escuela_nivel_pasos.paso_captura_id')
            ->where('escuela_nivel_pasos.escuela_nivel_id', $escuelaNivelId)
            ->where('escuela_nivel_pasos.estado', 'completado')
            ->pluck('pasos_captura.clave')
            ->all();
    }
}
