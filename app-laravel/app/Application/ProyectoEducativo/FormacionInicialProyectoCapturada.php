<?php

namespace App\Application\ProyectoEducativo;

/**
 * Lectura de la sección 11, bloque "Formación Docente Inicial": lo guardado del nivel tal como lo usa el formulario y si
 * está completa (mismas reglas que el guardado).
 */
class FormacionInicialProyectoCapturada extends FormacionProyectoCapturada
{
    /** @return array{politica_inicial: string|null, acciones: list<array<string, mixed>>} */
    public function datos(int $escuelaNivelId): array
    {
        $bloque = $this->bloque($escuelaNivelId, ReglasFormacionProyecto::TIPO_INICIAL);

        return ['politica_inicial' => $bloque['politica'], 'acciones' => $bloque['acciones']];
    }

    public function completa(int $escuelaNivelId): bool
    {
        $bloque = $this->bloque($escuelaNivelId, ReglasFormacionProyecto::TIPO_INICIAL);

        return ReglasFormacionProyecto::errores(ReglasFormacionProyecto::normalizar($bloque['politica'], $bloque['acciones'])) === [];
    }
}
