<?php

namespace App\Application\Documentos;

/** Resultado auditable de evaluar la aplicabilidad de un documento: estado, por qué, con qué fundamento y con qué certeza. */
final readonly class ResultadoAplicabilidad
{
    public function __construct(
        public Aplicabilidad $estado,
        public string $motivo,
        public string $fundamento,
        public string $certeza,
    ) {}
}
