<?php

namespace App\Infrastructure\Pdf;

use App\Application\Infraestructura\ConsultarAnexoInstalaciones;
use App\Models\EscuelaNivel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Anexo 2 Instalaciones del nivel, renderizado bajo demanda desde lo ya capturado (no persiste
 * nada). Una sola plantilla para todos los niveles; el modelo de lectura decide qué filas aplican.
 */
class AnexoInstalacionesPdf
{
    public function __construct(private readonly ConsultarAnexoInstalaciones $consulta) {}

    public function generar(EscuelaNivel $escuelaNivel): Response
    {
        $escuelaNivel->load(['nivelEducativo', 'escuela.responsableLegal.personaFisica', 'escuela.responsableLegal.personaMoral']);

        $pdf = Pdf::loadView('pdf.anexo2-instalaciones', [
            'anexo' => $this->consulta->ejecutar($escuelaNivel),
            'nivelNombre' => $escuelaNivel->nivelEducativo->nombre,
            'escuela' => $escuelaNivel->escuela,
        ]);

        // Pie "Página N de M": el total solo se conoce tras renderizar.
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $canvas->page_text($canvas->get_width() / 2 - 40, $canvas->get_height() - 30, 'Página {PAGE_NUM} de {PAGE_COUNT}', $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans') ?? 'helvetica', 8);

        return $pdf->download("anexo-2-instalaciones-{$escuelaNivel->nivelEducativo->clave}.pdf");
    }
}
