<?php

namespace App\Domain\Documentos;

use App\Domain\Validaciones\Documental\NormalizadorDomicilio;

/**
 * Compara dos domicilios como evidencia para decidir, nunca como autoridad jurídica (spec 2026-10-20 §7).
 * Reutiliza NormalizadorDomicilio del motor documental: acentos, mayúsculas, espacios, «Av.» = «Avenida»,
 * «No.» = «Número». No modifica los valores originales, no usa servicios externos y no corrige nada.
 *
 * Calle, número exterior, colonia, municipio y código postal deben ser comparables y coincidir para acreditar
 * coincidencia; el número interior solo se compara si ambas fuentes lo traen. «S/N» declara «sin número».
 * Si alguna parte difiere o falta, el resultado es evidencia insuficiente: ausencia de evidencia no es
 * ausencia de discrepancia, y un texto distinto tampoco es una discrepancia confirmada.
 */
final class ComparadorDomicilios
{
    private const OBLIGATORIAS = ['calle', 'numeroExt', 'colonia', 'municipio', 'codigoPostal'];

    public function __construct(private readonly NormalizadorDomicilio $normalizador) {}

    public function comparar(Domicilio $a, Domicilio $b): ComparacionDomicilios
    {
        $coinciden = [];
        $difieren = [];
        $noComparables = [];

        foreach (['calle', 'numeroExt', 'numeroInt', 'colonia', 'municipio', 'codigoPostal'] as $parte) {
            $x = $this->valor($parte, $a->{$parte});
            $y = $this->valor($parte, $b->{$parte});

            if ($x === null || $y === null) {
                $noComparables[] = $parte;
            } elseif ($x === $y) {
                $coinciden[] = $parte;
            } else {
                $difieren[] = $parte;
            }
        }

        // El número interior es opcional: si una fuente no lo trae no hay nada que contrastar.
        $noComparables = array_values(array_diff($noComparables, ['numeroInt']));

        $acreditada = $difieren === [] && array_diff(self::OBLIGATORIAS, $coinciden) === [];

        return new ComparacionDomicilios(
            $acreditada ? ResultadoComparacion::CoincidenciaAcreditada : ResultadoComparacion::EvidenciaInsuficiente,
            $coinciden,
            $difieren,
            $noComparables,
        );
    }

    /** Valor normalizado para comparar; null = la fuente no lo declara; '' = «sin número». */
    private function valor(string $parte, ?string $original): ?string
    {
        if ($original === null || trim($original) === '') {
            return null;
        }

        if ($parte === 'codigoPostal') {
            $digitos = preg_replace('/\D/', '', $original);

            return $digitos === '' ? null : $digitos;
        }

        if (in_array($parte, ['numeroExt', 'numeroInt'], true) && preg_match('/^\s*s\s*\/?\s*n\s*$/i', $original) === 1) {
            return '';
        }

        $normalizado = $this->normalizador->normalizar($original);

        return $normalizado === '' ? null : $normalizado;
    }
}
