<?php

namespace App\Application\Infraestructura;

/**
 * Categorías de sanitario aplicables por nivel educativo — única fuente,
 * para que InfraestructuraNivel (presentación) y RegistrarInfraestructuraNivel
 * (invariante de escritura, WS-2.4a) no puedan divergir.
 *
 * COMPENDIO (línea 132) enumera cuatro categorías para el formulario
 * genérico de Básica; el DDL (CHECK de sanitarios.categoria) añade
 * alumnado_maternal y personal, que solo tienen sentido en Inicial. Ese
 * reparto es la inferencia aplicada aquí.
 */
class CategoriasSanitariosPorNivel
{
    private const INICIAL = ['alumnado_maternal', 'personal'];

    private const BASICA = ['alumnado_masculino', 'alumnado_femenino', 'personal_masculino', 'personal_femenino'];

    /** @return list<string> */
    public function paraNivel(string $nivelEducativoClave): array
    {
        return $nivelEducativoClave === 'inicial' ? self::INICIAL : self::BASICA;
    }
}
