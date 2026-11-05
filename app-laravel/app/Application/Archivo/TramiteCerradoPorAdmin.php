<?php

namespace App\Application\Archivo;

/** Un admin archivó (cerró) un trámite por su cuenta; no se despacha en cascada de cuenta ni cuando archiva el dueño. */
final class TramiteCerradoPorAdmin
{
    public function __construct(public readonly int $escuelaId) {}
}
