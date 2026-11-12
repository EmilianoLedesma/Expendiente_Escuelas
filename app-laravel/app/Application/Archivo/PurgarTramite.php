<?php

namespace App\Application\Archivo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Infrastructure\Pdf\ReporteValidacionPdf;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use App\Models\ProyectoEducativoPaec;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Scopes\NivelDeEscuelaNoArchivada;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purga (borrado definitivo, solo admin) de un trámite ya archivado: la escuela y todo lo
 * que cuelga de ella vía ON DELETE CASCADE, más sus documentos y reportes de validación en
 * disco. El plantel y sus documentos NO se tocan: el plantel puede compartirse.
 * Cuerpo heredado del antiguo EliminarTramite (Ruling R1).
 */
class PurgarTramite
{
    public function __construct(
        private readonly AlmacenDocumentos $almacen,
        private readonly ReporteValidacionPdf $reportes,
        private readonly RegistrarAccion $registrar,
    ) {}

    /** @throws PrecondicionIncumplida|DatosInvalidos */
    public function ejecutar(int $actorId, int $escuelaId, string $motivo, string $confirmacion): void
    {
        Purga::validar($motivo, $confirmacion);

        DB::transaction(function () use ($actorId, $escuelaId, $motivo) {
            $actor = User::find($actorId) ?? throw new PrecondicionIncumplida('rol', 'No tienes permiso para esta acción.');
            RolesSedeq::exigir($actor, RolesSedeq::ADMIN);

            $this->purgar($actorId, $escuelaId, $motivo);
        });
    }

    /**
     * Interno, compartido con PurgarCuenta: quien llama ya validó compuertas y admin, y
     * corre dentro de su transacción.
     *
     * @throws PrecondicionIncumplida si el trámite no existe o no está archivado.
     */
    public function purgar(int $actorId, int $escuelaId, string $motivo): void
    {
        // Orden de bloqueo del proyecto: escuela_niveles antes que escuelas.
        $niveles = EscuelaNivel::withoutGlobalScope(NivelDeEscuelaNoArchivada::class)
            ->where('escuela_id', $escuelaId)->orderBy('id')->lockForUpdate()->pluck('id');
        $escuela = Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->lockForUpdate()->find($escuelaId)
            ?? throw new PrecondicionIncumplida('archivo', 'El trámite no existe.');

        if ($escuela->archivado_at === null) {
            throw new PrecondicionIncumplida('archivo', 'Solo se puede purgar un trámite archivado.');
        }

        // Rutas reunidas ANTES del borrado: después, el cascade ya se llevó las filas.
        $documentos = [
            ...DocumentoEscuela::where('escuela_id', $escuelaId)->whereNotNull('archivo_path')->lockForUpdate()->pluck('archivo_path'),
            ...DocumentoEscuelaNivel::whereIn('escuela_nivel_id', $niveles)->whereNotNull('archivo_path')->lockForUpdate()->pluck('archivo_path'),
            ...ProyectoEducativoPaec::rutasDe($niveles),
        ];
        $reportes = EvaluacionValidacion::where('escuela_id', $escuelaId)->pluck('archivo_path')->all();

        $escuela->delete();

        $this->registrar->ejecutar($actorId, RegistrarAccion::PURGAR_TRAMITE, 'escuela', $escuelaId, $motivo, [
            'niveles' => $niveles->count(), 'documentos' => count($documentos), 'reportes' => count($reportes),
        ]);

        // Los archivos se borran solo si el borrado de filas se confirma; un fallo de disco
        // se reporta, nunca revierte. El log lleva solo ids.
        DB::afterCommit(function () use ($escuelaId, $documentos, $reportes) {
            Log::info('Trámite eliminado', ['escuela_id' => $escuelaId, 'purgado_en' => now()->toIso8601String()]);

            foreach ($documentos as $ruta) {
                rescue(fn () => $this->almacen->eliminar($ruta), report: true);
            }
            foreach ($reportes as $ruta) {
                rescue(fn () => $this->reportes->eliminar($ruta), report: true);
            }
        });
    }
}
