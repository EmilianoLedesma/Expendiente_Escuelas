<?php

namespace App\Application\Documentos;

use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\TipoDocumento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Fuente de qué documentos ya capturó una escuela para las pantallas de
 * Paso 2.2 (checklist y descarga). No reemplaza
 * DocumentosCompletos::clavesPendientes(), que es la que realmente decide
 * si el paso está completo (usada por avanzar()) — las dos consultas
 * divergen hoy en cómo tratan el ambito y una fila de catálogo faltante;
 * ver docs/reports/2026-09-28-ws4-frontera-lecturas.md.
 *
 * Antes había dos implementaciones independientes de esta pregunta:
 * Paso2Documentos::documentosCapturados() (para la pantalla) y
 * ObtenerDocumentoCapturado::ejecutar() (para la descarga), cada una con
 * su propia consulta escuela-vs-plantel por ambito — exactamente el
 * patrón de duplicación que ya causó un defecto real antes (ver
 * docs/progress.md, WS-2 item 2.1, campoFutbol). Ambas ahora llaman aquí.
 */
class DocumentosCapturados
{
    public function __construct(
        private readonly DocumentosCompletos $documentosCompletos,
        private readonly DocumentosNivelCompletos $documentosNivelCompletos,
    ) {}

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

    /**
     * Paso 2.4 (WS-5b): documentos ya capturados del nivel, solo de claves
     * aplicables — mismo contrato que paraEscuela().
     *
     * @return Collection<string, array{archivoPath: string, nombreArchivo: string, subidoEn: Carbon}>
     */
    public function paraEscuelaNivel(int $escuelaNivelId): Collection
    {
        $clavesPorId = TipoDocumento::whereIn('clave', $this->documentosNivelCompletos->clavesAplicables($escuelaNivelId))->pluck('clave', 'id');
        $capturados = collect();

        foreach (DocumentoEscuelaNivel::where('escuela_nivel_id', $escuelaNivelId)->whereIn('tipo_documento_id', $clavesPorId->keys())->get() as $documento) {
            $capturados[$clavesPorId[$documento->tipo_documento_id]] = [
                'archivoPath' => (string) $documento->archivo_path,
                'nombreArchivo' => basename((string) $documento->archivo_path),
                'subidoEn' => $documento->updated_at,
            ];
        }

        return $capturados;
    }
}
