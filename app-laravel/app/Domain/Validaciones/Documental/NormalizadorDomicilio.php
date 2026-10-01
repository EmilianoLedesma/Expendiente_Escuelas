<?php

namespace App\Domain\Validaciones\Documental;

use Normalizer;

/**
 * Deterministic address normalization for comparison only. Keeps digits
 * (unlike NormalizadorNombre), expands common Mexican street abbreviations
 * and drops filler words that one source writes and the other omits
 * ("Calle", "Col.", "No."). No fuzzy matching.
 */
final class NormalizadorDomicilio
{
    private const EXPANSIONES = [
        'AV' => 'AVENIDA',
        'AVE' => 'AVENIDA',
        'BLVD' => 'BOULEVARD',
        'BLVR' => 'BOULEVARD',
        'BOUL' => 'BOULEVARD',
        'PROL' => 'PROLONGACION',
        'CDA' => 'CERRADA',
        'CALZ' => 'CALZADA',
        'FRACC' => 'FRACCIONAMIENTO',
    ];

    private const OMITIDAS = ['CALLE', 'COL', 'COLONIA', 'NO', 'NUM', 'NUMERO'];

    public function normalizar(string $texto): string
    {
        $descompuesto = Normalizer::normalize($texto, Normalizer::FORM_D);
        $sinMarcas = preg_replace('/\p{Mn}+/u', '', $descompuesto === false ? $texto : $descompuesto);
        $mayusculas = mb_strtoupper((string) $sinMarcas, 'UTF-8');
        $limpio = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $mayusculas));

        if ($limpio === '') {
            return '';
        }

        $tokens = [];
        foreach (explode(' ', $limpio) as $token) {
            if (in_array($token, self::OMITIDAS, true)) {
                continue;
            }
            $tokens[] = self::EXPANSIONES[$token] ?? $token;
        }

        return implode(' ', $tokens);
    }
}
