<?php

namespace App\Domain\Validaciones\Documental;

use App\Domain\Validaciones\Resultado\ResultadoRegla;

interface ReglaDocumental
{
    public function evaluar(ContextoValidacion $contexto): ResultadoRegla;
}
