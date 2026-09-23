<?php

namespace App\Http\Controllers\Tramite;

use App\Infrastructure\Pdf\FormatoSolicitudPdf;
use App\Models\Escuela;
use Illuminate\Http\Response;

/**
 * Sin caso de uso de Application a propósito: no hay regla ni persistencia,
 * solo el renderizado de solo lectura que ya vive en Infrastructure/Pdf.
 */
class FormatoSolicitudPdfController
{
    public function __invoke(Escuela $escuela, FormatoSolicitudPdf $pdf): Response
    {
        return $pdf->generar($escuela);
    }
}
