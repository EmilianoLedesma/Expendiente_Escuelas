<?php

namespace App\Application\ProyectoEducativo;

use App\Models\ProyectoEducativoBibliografia;

/** Lectura de la sección 20: las referencias guardadas del nivel (en su orden) y si está completa (al menos una y todas válidas). */
class BibliografiaProyectoCapturada
{
    /** @return list<array{autores: string, anio: string, titulo: string, fuente: string, url: string}> */
    public function referencias(int $escuelaNivelId): array
    {
        return ProyectoEducativoBibliografia::where('escuela_nivel_id', $escuelaNivelId)->orderBy('orden')->get()
            ->map(fn (ProyectoEducativoBibliografia $fila): array => [
                'autores' => $fila->autores,
                'anio' => (string) $fila->anio,
                'titulo' => $fila->titulo,
                'fuente' => $fila->fuente,
                'url' => (string) $fila->url,
            ])->values()->all();
    }

    public function completa(int $escuelaNivelId): bool
    {
        return ReglasBibliografiaProyecto::errores($this->referencias($escuelaNivelId)) === [];
    }
}
