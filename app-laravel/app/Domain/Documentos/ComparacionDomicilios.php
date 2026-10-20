<?php

namespace App\Domain\Documentos;

/** Resultado detallado de comparar dos domicilios: qué partes coinciden, difieren o no se pudieron comparar. */
final readonly class ComparacionDomicilios
{
    /**
     * @param  list<string>  $partesQueCoinciden
     * @param  list<string>  $partesQueDifieren
     * @param  list<string>  $partesNoComparables  alguna de las dos fuentes no la declara
     */
    public function __construct(
        public ResultadoComparacion $resultado,
        public array $partesQueCoinciden,
        public array $partesQueDifieren,
        public array $partesNoComparables,
    ) {}
}
