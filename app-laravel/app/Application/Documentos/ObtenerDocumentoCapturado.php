<?php

namespace App\Application\Documentos;

use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\ResponsableLegal;
use App\Models\TipoDocumento;

/**
 * Consulta de lectura para la descarga de un documento de Paso 2.2: devuelve
 * la ruta del archivo capturado, o null si la clave no aplica al tipo_persona
 * de la escuela (lista de DocumentosCompletos::clavesAplicables, única fuente),
 * si aún no hay responsable legal, o si el documento no está capturado.
 * La propiedad de la escuela ya la decidió la Policy antes de llegar aquí.
 */
class ObtenerDocumentoCapturado
{
    public function __construct(private readonly DocumentosCompletos $documentosCompletos) {}

    public function ejecutar(int $escuelaId, string $clave): ?string
    {
        $tipoPersona = ResponsableLegal::where('escuela_id', $escuelaId)->value('tipo_persona');

        if ($tipoPersona === null || ! in_array($clave, $this->documentosCompletos->clavesAplicables($tipoPersona), true)) {
            return null;
        }

        $tipo = TipoDocumento::where('clave', $clave)->first();

        if ($tipo === null) {
            return null;
        }

        return $tipo->ambito === 'plantel'
            ? DocumentoPlantel::where('plantel_id', Escuela::whereKey($escuelaId)->value('plantel_id'))->where('tipo_documento_id', $tipo->id)->value('archivo_path')
            : DocumentoEscuela::where('escuela_id', $escuelaId)->where('tipo_documento_id', $tipo->id)->value('archivo_path');
    }
}
