<?php

namespace App\Application\Documentos;

use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\TipoDocumento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Única fuente de "¿qué documentos de Paso 2.2 ya capturó esta escuela?".
 * Antes había dos implementaciones independientes de la misma pregunta:
 * Paso2Documentos::documentosCapturados() (para la pantalla) y
 * ObtenerDocumentoCapturado::ejecutar() (para la descarga), cada una con
 * su propia consulta escuela-vs-plantel por ambito — exactamente el
 * patrón de duplicación que ya causó un defecto real antes (ver
 * docs/progress.md, WS-2 item 2.1, campoFutbol). Ambas ahora llaman aquí.
 */
class DocumentosCapturados
{
    public function __construct(private readonly DocumentosCompletos $documentosCompletos) {}

    /** @return Collection<string, array{archivoPath: string, nombreArchivo: string, subidoEn: Carbon}> */
    public function paraEscuela(int $escuelaId, string $tipoPersona): Collection
    {
        $escuela = Escuela::findOrFail($escuelaId);
        $claves = $this->documentosCompletos->clavesAplicables($tipoPersona);
        $tipos = TipoDocumento::whereIn('clave', $claves)->get()->keyBy('clave');

        $capturados = collect();

        foreach ($claves as $clave) {
            $tipo = $tipos[$clave] ?? null;

            if ($tipo === null) {
                continue;
            }

            $documento = $tipo->ambito === 'plantel'
                ? DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->where('tipo_documento_id', $tipo->id)->first()
                : DocumentoEscuela::where('escuela_id', $escuelaId)->where('tipo_documento_id', $tipo->id)->first();

            if ($documento === null) {
                continue;
            }

            $capturados[$clave] = [
                'archivoPath' => (string) $documento->archivo_path,
                'nombreArchivo' => basename((string) $documento->archivo_path),
                'subidoEn' => $documento->updated_at,
            ];
        }

        return $capturados;
    }
}
