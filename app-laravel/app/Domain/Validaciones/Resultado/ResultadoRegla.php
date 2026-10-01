<?php

namespace App\Domain\Validaciones\Resultado;

final readonly class ResultadoRegla
{
    /**
     * @param  array<string, mixed>  $detalles
     * @param  list<string>  $documentos  tipos_documentos.clave the applicant should look at to fix this result
     */
    public function __construct(
        public string $clave,
        public EstadoResultado $estado,
        public string $mensaje,
        public array $detalles = [],
        public array $documentos = [],
    ) {}
}
