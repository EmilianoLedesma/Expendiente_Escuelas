<?php

namespace App\Application\ProyectoEducativo;

use App\Models\EscuelaNivel;

/**
 * Domicilio que muestra la sección 1 (Presentación): el del plantel del nivel, leído tal cual. No se captura ni se
 * guarda otra vez en el proyecto educativo (3FN: el plantel es la única fuente del domicilio).
 */
class DomicilioPlantelDelNivel
{
    /** @return list<array{string, string}> pares «Etiqueta / valor», sin las partes vacías */
    public function ejecutar(int $escuelaNivelId): array
    {
        $plantel = EscuelaNivel::findOrFail($escuelaNivelId)->escuela->plantel;

        $partes = [
            'Calle' => $plantel->calle,
            'Número exterior' => $plantel->numero_ext,
            'Número interior' => $plantel->numero_int,
            'Colonia' => $plantel->colonia,
            'Localidad' => $plantel->localidad,
            'Municipio' => $plantel->municipio,
            'Código postal' => $plantel->codigo_postal,
        ];

        $salida = [];
        foreach ($partes as $etiqueta => $valor) {
            if ($valor !== null && trim($valor) !== '') {
                $salida[] = [$etiqueta, trim($valor)];
            }
        }

        return $salida;
    }
}
