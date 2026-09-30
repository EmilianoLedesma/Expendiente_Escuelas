<?php

namespace App\Infrastructure\Pdf;

use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\SeccionCapacidad;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders the final documental validation as a formatted PDF and stores it
 * on the private 'documentos' disk (owner decision, ADR-007: every report
 * is kept). Unique path per run, same convention as AlmacenDocumentosLocal.
 */
class ReporteValidacionPdf
{
    /**
     * @param  array{numero: string, nombre: string|null, domicilio: string}  $escuela
     * @param  list<FilaValidacion>  $filas
     * @param  list<SeccionCapacidad>  $capacidad
     */
    public function guardar(int $escuelaId, array $escuela, bool $listaParaEnvio, array $filas, array $capacidad, DateTimeInterface $generadaEn): string
    {
        $ruta = "validaciones/{$escuelaId}/reporte-".Str::ulid().'.pdf';

        $pdf = Pdf::loadView('pdf.reporte-validacion', [
            'escuela' => $escuela,
            'listaParaEnvio' => $listaParaEnvio,
            'filas' => $filas,
            'capacidad' => $capacidad,
            'generadaEn' => $generadaEn,
        ]);

        Storage::disk('documentos')->put($ruta, $pdf->output());

        return $ruta;
    }

    public function eliminar(string $ruta): void
    {
        Storage::disk('documentos')->delete($ruta);
    }
}
