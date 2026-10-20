<?php

namespace App\Application\Documentos;

/**
 * Resultado auditable de evaluar la aplicabilidad de un documento: estado, por qué, con qué fundamento, con qué
 * certeza y, cuando hubo comparación, qué documentos se usaron como evidencia (ids de documentos_plantel).
 */
final readonly class ResultadoAplicabilidad
{
    /** @param  list<int>  $evidencia */
    public function __construct(
        public Aplicabilidad $estado,
        public string $motivo,
        public string $fundamento,
        public string $certeza,
        public array $evidencia = [],
    ) {}
}
