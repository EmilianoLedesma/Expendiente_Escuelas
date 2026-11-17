<?php

namespace App\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosBibliotecaProyecto;
use App\Models\ProyectoEducativoBiblioteca;

/** Lectura de la sección 19: lo guardado del nivel (las respuestas Sí/No como 'si' o 'no') y si está completa (los dos bloques). */
class BibliotecaProyectoCapturada
{
    private const SI_NO = ['biblioteca_tiene_encargado', 'biblioteca_tiene_programa_lectura'];

    /** @return array<string, string|null> campo => valor (null si aún no hay fila o su bloque no se ha guardado) */
    public function datos(int $escuelaNivelId): array
    {
        $fila = ProyectoEducativoBiblioteca::where('escuela_nivel_id', $escuelaNivelId)->first();

        $datos = [];
        foreach (DatosBibliotecaProyecto::campos() as $campo) {
            $valor = $fila?->getAttribute($campo);
            $datos[$campo] = in_array($campo, self::SI_NO, true) && $valor !== null ? ($valor ? 'si' : 'no') : $valor;
        }

        return $datos;
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasBibliotecaProyecto::errores($this->datos($escuelaNivelId)) === [];
    }
}
