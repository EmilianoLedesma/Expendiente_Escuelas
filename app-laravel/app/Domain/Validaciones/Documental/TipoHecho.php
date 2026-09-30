<?php

namespace App\Domain\Validaciones\Documental;

/**
 * Closed catalog of fact types; mirrors the CHECK on
 * hechos_documento.tipo_hecho. Adding a case requires a migration too.
 */
enum TipoHecho: string
{
    case NombreTitular = 'nombre_titular';
    case Curp = 'curp';
}
