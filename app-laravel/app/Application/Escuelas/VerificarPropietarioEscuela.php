<?php

namespace App\Application\Escuelas;

use App\Models\Escuela;

/**
 * Decisión pura de propiedad (ADR-002): una Escuela pertenece a un
 * Solicitante vía escuelas.solicitante_id. EscuelaPolicy delega aquí en
 * vez de reimplementar la comparación — este método es también lo que la
 * futura API de Etapa 3 llama directamente, sin pasar por una Policy HTTP.
 */
class VerificarPropietarioEscuela
{
    public function ejecutar(int $escuelaId, int $solicitanteId): bool
    {
        return Escuela::where('id', $escuelaId)
            ->where('solicitante_id', $solicitanteId)
            ->exists();
    }
}
