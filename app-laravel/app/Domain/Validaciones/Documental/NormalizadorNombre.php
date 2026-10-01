<?php

namespace App\Domain\Validaciones\Documental;

use Normalizer;

/**
 * Deterministic name normalization for comparison only: strip diacritics
 * (á→A, Ñ→N, ü→U), uppercase, turn punctuation into spaces, collapse
 * whitespace. Uses ext-intl, not Illuminate\Support\Str (ADR-001).
 */
final class NormalizadorNombre
{
    public function normalizar(string $nombre): string
    {
        $descompuesto = Normalizer::normalize($nombre, Normalizer::FORM_D);
        $sinMarcas = preg_replace('/\p{Mn}+/u', '', $descompuesto === false ? $nombre : $descompuesto);
        $mayusculas = mb_strtoupper((string) $sinMarcas, 'UTF-8');
        $soloLetras = preg_replace('/[^\p{L}]+/u', ' ', $mayusculas);

        return trim((string) $soloLetras);
    }

    /** @return list<string> */
    public function tokens(string $nombre): array
    {
        $normalizado = $this->normalizar($nombre);

        return $normalizado === '' ? [] : explode(' ', $normalizado);
    }
}
