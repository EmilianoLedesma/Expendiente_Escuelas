<?php

namespace App\Application\Captura;

use Attribute;

/**
 * Marca cómo se normaliza una propiedad de texto de un formulario. Sin este
 * atributo, toda propiedad de texto se normaliza como Normalizacion::Texto
 * (ver App\Livewire\Hooks\LimpiarYValidarAlCapturar).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Normalizar
{
    public function __construct(public readonly Normalizacion $como) {}
}
