<?php

namespace App\Infrastructure\Pdf;

use App\Application\Infraestructura\DTO\AnexoInstalaciones;
use App\Models\EscuelaNivel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Anexo 2 Instalaciones del nivel, renderizado bajo demanda desde lo ya capturado (no persiste
 * nada). Una sola plantilla para todos los niveles; el modelo de lectura (que corre el controlador)
 * decide qué filas aplican. Solo se importa el DTO de Application: el PDF no invoca casos de uso.
 */
class AnexoInstalacionesPdf
{
    public function generar(EscuelaNivel $escuelaNivel, AnexoInstalaciones $anexo): Response
    {
        $escuelaNivel->load(['nivelEducativo', 'escuela.responsableLegal.personaFisica', 'escuela.responsableLegal.personaMoral']);

        $pdf = Pdf::loadView('pdf.anexo2-instalaciones', [
            'anexo' => $anexo,
            'nivelNombre' => $escuelaNivel->nivelEducativo->nombre,
            'nivelClave' => $escuelaNivel->nivelEducativo->clave,
            'escuela' => $escuelaNivel->escuela,
        ]);

        // Pie "Página N de M": page_text necesita el canvas ya paginado, y {PAGE_COUNT} solo se conoce tras renderizar.
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $canvas->page_text($canvas->get_width() / 2 - 40, $canvas->get_height() - 30, 'Página {PAGE_NUM} de {PAGE_COUNT}', $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans') ?? 'helvetica', 8);

        return $pdf->download("anexo-2-instalaciones-{$escuelaNivel->nivelEducativo->clave}.pdf");
    }
}
