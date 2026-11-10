<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosPresentacionProyecto;
use App\Models\ProyectoEducativoPresentacion;

/** Lectura de la sección 1: lo guardado del nivel y si está completa (mismas reglas que el guardado). */
class PresentacionProyectoCapturada
{
    /** @return array<string, string|null> campo => valor (todo null si aún no hay fila) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoPresentacion::where('escuela_nivel_id', $escuelaNivelId)->first();

        return array_combine(
            DatosPresentacionProyecto::campos(),
            array_map(fn (string $campo) => $fila?->getAttribute($campo), DatosPresentacionProyecto::campos()),
        );
    }

    /** Primer paso (1 a 3) con requeridos incompletos en lo guardado; 1 si nada falta o no hay nada guardado. */
    public function pasoInicial(int $escuelaNivelId): int
    {
        $datos = $this->datos($escuelaNivelId);

        foreach (ReglasPresentacionProyecto::PASOS as $numero => $paso) {
            if (ReglasPresentacionProyecto::errores($datos, completa: true, campos: $paso['campos']) !== []) {
                return $numero;
            }
        }

        return 1;
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasPresentacionProyecto::errores($this->datos($escuelaNivelId), completa: true) === [];
    }
}
