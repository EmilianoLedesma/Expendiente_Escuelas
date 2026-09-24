<?php

namespace App\Application\Excepciones;

use DomainException;

/**
 * Un caso de uso recibió datos que violan un invariante de entrada (valor
 * fuera de rango, enum no permitido, referencia no aplicable). $errores usa
 * la misma ruta de campo que la propiedad Livewire correspondiente
 * (p. ej. "espacios.{tipoEspacioId}.cantidad", "archivos.{clave}") para que
 * el adaptador pueda mapearlo 1:1 con addError(), sin recalcular nada.
 */
final class DatosInvalidos extends DomainException
{
    /** @param array<string, string> $errores campo => mensaje */
    public function __construct(public readonly array $errores)
    {
        parent::__construct('Datos inválidos: '.implode(' ', $errores));
    }
}
