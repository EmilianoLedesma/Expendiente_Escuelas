<?php

namespace App\Application\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Infrastructure\Pdf\ReporteValidacionPdf;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Borrado definitivo de un trámite (la escuela y todo lo que cuelga de ella,
 * vía ON DELETE CASCADE) mientras todos sus niveles sigan en captura. La
 * propiedad la decide EscuelaPolicy::delete antes de llegar aquí. El plantel
 * y sus documentos NO se tocan: el plantel puede compartirse o reutilizarse.
 * Los reportes de validación también se borran: esto resuelve en parte la
 * retención de evaluaciones que ADR-007 dejó pendiente (la política general
 * de limpieza sigue abierta).
 */
class EliminarTramite
{
    public function __construct(
        private readonly AlmacenDocumentos $almacen,
        private readonly ReporteValidacionPdf $reportes,
    ) {}

    /** @throws PrecondicionIncumplida si algún nivel ya no está en captura. */
    public function ejecutar(int $escuelaId): void
    {
        DB::transaction(function () use ($escuelaId) {
            // WS-7a: orden de bloqueo de TramiteEditable — escuela_niveles antes que escuelas.
            $niveles = EscuelaNivel::where('escuela_id', $escuelaId)->orderBy('id')->lockForUpdate()->pluck('estado_id', 'id');
            $escuela = Escuela::lockForUpdate()->findOrFail($escuelaId);

            if (! TramiteEditable::todosEnCaptura($niveles, TramiteEditable::idEnCaptura())) {
                throw new PrecondicionIncumplida(TramiteEditable::EN_CAPTURA, 'Solo se puede eliminar un trámite mientras todos sus niveles estén en captura.');
            }

            // Rutas reunidas ANTES del borrado: después, el cascade ya se llevó las filas.
            $documentos = [
                // lockForUpdate: un RegistrarDocumento concurrente no puede reemplazar la ruta y dejar un archivo huérfano.
                ...DocumentoEscuela::where('escuela_id', $escuelaId)->whereNotNull('archivo_path')->lockForUpdate()->pluck('archivo_path'),
                ...DocumentoEscuelaNivel::whereIn('escuela_nivel_id', $niveles->keys())->whereNotNull('archivo_path')->lockForUpdate()->pluck('archivo_path'),
            ];
            $reportes = EvaluacionValidacion::where('escuela_id', $escuelaId)->pluck('archivo_path')->all();

            $escuela->delete();

            // Igual que RegistrarDocumento: los archivos se borran solo si el borrado
            // de filas se confirma; un fallo de disco se reporta, nunca revierte.
            DB::afterCommit(function () use ($escuela, $documentos, $reportes) {
                Log::info('Trámite eliminado', [
                    'escuela_id' => $escuela->id,
                    'solicitante_id' => $escuela->solicitante_id,
                    'eliminado_en' => now()->toIso8601String(),
                ]);

                foreach ($documentos as $ruta) {
                    rescue(fn () => $this->almacen->eliminar($ruta), report: true);
                }
                foreach ($reportes as $ruta) {
                    rescue(fn () => $this->reportes->eliminar($ruta), report: true);
                }
            });
        });
    }
}
