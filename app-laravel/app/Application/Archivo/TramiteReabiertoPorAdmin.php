<?php

namespace App\Application\Archivo;

/** Un admin restauró (reabrió) un trámite por su cuenta; no se despacha en cascada de cuenta. */
final class TramiteReabiertoPorAdmin
{
    public function __construct(public readonly int $escuelaId) {}
}
