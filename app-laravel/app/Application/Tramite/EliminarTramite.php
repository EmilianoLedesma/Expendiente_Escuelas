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
use Illuminate\Support\Collection;
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
    public const EN_CAPTURA = 'en_captura';

    public function __construct(
        private readonly AlmacenDocumentos $almacen,
        private readonly ReporteValidacionPdf $reportes,
    ) {}

    public static function idEnCaptura(): ?int
    {
        $id = DB::table('estados_expediente')->where('clave', self::EN_CAPTURA)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * La regla de borrado, compartida con ResumenTramite (puedeEliminar). Sin niveles también es true.
     *
     * @param  Collection<array-key, mixed>  $estadoIds  estado_id de cada escuela_nivel
     * @param  int|null  $enCapturaId  idEnCaptura(); quien evalúa muchos trámites lo busca una vez y lo pasa
     */
    public static function todosEnCaptura(Collection $estadoIds, ?int $enCapturaId): bool
    {
        return $estadoIds->every(fn ($estadoId) => (int) $estadoId === $enCapturaId);
    }

    /** @throws PrecondicionIncumplida si algún nivel ya no está en captura. */
    public function ejecutar(int $escuelaId): void
    {
        DB::transaction(function () use ($escuelaId) {
            $escuela = Escuela::lockForUpdate()->findOrFail($escuelaId);
            $niveles = EscuelaNivel::where('escuela_id', $escuelaId)->lockForUpdate()->pluck('estado_id', 'id');

            if (! self::todosEnCaptura($niveles, self::idEnCaptura())) {
                throw new PrecondicionIncumplida(self::EN_CAPTURA, 'Solo se puede eliminar un trámite mientras todos sus niveles estén en captura.');
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
