<?php

namespace App\Application\Documentos;

use App\Application\ResponsableLegal\TipoPersonaDeEscuela;

/**
 * Consulta de lectura para la descarga de un documento de Paso 2.2: devuelve
 * la ruta del archivo capturado, o null si no hay responsable legal, si la
 * clave no aplica al tipo_persona de la escuela, o si el documento no está
 * capturado. La propiedad de la escuela ya la decidió la Policy antes de
 * llegar aquí. Delega la resolución de tipo_persona y la búsqueda del
 * documento en TipoPersonaDeEscuela/DocumentosCapturados — única fuente de
 * cada pregunta, compartida con Paso2Documentos (ADR-006).
 */
class ObtenerDocumentoCapturado
{
    public function __construct(
        private readonly TipoPersonaDeEscuela $tipoPersonaDeEscuela,
        private readonly DocumentosCapturados $documentosCapturados,
    ) {}

    public function ejecutar(int $escuelaId, string $clave): ?string
    {
        $tipoPersona = $this->tipoPersonaDeEscuela->ejecutar($escuelaId);

        if ($tipoPersona === null) {
            return null;
        }

        $capturados = $this->documentosCapturados->paraEscuela($escuelaId, $tipoPersona);

        return $capturados[$clave]['archivoPath'] ?? null;
    }
}
