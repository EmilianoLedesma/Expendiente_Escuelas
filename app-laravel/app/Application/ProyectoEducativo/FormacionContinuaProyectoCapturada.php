<?php

namespace App\Application\ProyectoEducativo;

use App\Models\ProyectoEducativoFormacionPolitica;

/**
 * Lectura de la sección 11, bloque "Formación Docente Continua": lo guardado del nivel (política, nombre y páginas del PDF
 * de planeación, acciones; nunca la ruta de disco) y si está completa (mismas reglas que el guardado).
 */
class FormacionContinuaProyectoCapturada extends FormacionProyectoCapturada
{
    /** @return array{politica_continua: string|null, planeacion_nombre: string|null, planeacion_paginas: int|null, acciones_continua: list<array<string, mixed>>} */
    public function datos(int $escuelaNivelId): array
    {
        $bloque = $this->bloque($escuelaNivelId, ReglasFormacionProyecto::TIPO_CONTINUA);
        $fila = $this->fila($escuelaNivelId);

        return [
            'politica_continua' => $bloque['politica'],
            'planeacion_nombre' => $fila?->planeacion_nombre,
            'planeacion_paginas' => $fila?->planeacion_paginas,
            'acciones_continua' => $bloque['acciones'],
        ];
    }

    public function completa(int $escuelaNivelId): bool
    {
        $bloque = $this->bloque($escuelaNivelId, ReglasFormacionProyecto::TIPO_CONTINUA);

        return $this->fila($escuelaNivelId)?->planeacion_path !== null
            && ReglasFormacionProyecto::errores(ReglasFormacionProyecto::normalizar($bloque['politica'], $bloque['acciones']), 'politica_continua', 'acciones_continua') === [];
    }

    private function fila(int $escuelaNivelId): ?ProyectoEducativoFormacionPolitica
    {
        return ProyectoEducativoFormacionPolitica::where(['escuela_nivel_id' => $escuelaNivelId, 'tipo' => ReglasFormacionProyecto::TIPO_CONTINUA])->first();
    }
}
