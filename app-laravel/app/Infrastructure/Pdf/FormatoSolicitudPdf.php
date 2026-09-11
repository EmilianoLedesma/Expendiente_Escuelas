<?php

namespace App\Infrastructure\Pdf;

use App\Models\Escuela;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Renderizado bajo demanda desde datos ya capturados por
 * RegistrarResponsableLegal — no persiste nada, es una respuesta de
 * descarga (docs/superpowers/specs/2026-09-10-paso2-documentos-design.md §7).
 */
class FormatoSolicitudPdf
{
    public function generar(Escuela $escuela): Response
    {
        $escuela->load(['responsableLegal.personaFisica', 'responsableLegal.personaMoral', 'ternasNombres']);

        return Pdf::loadView('pdf.formato-solicitud', ['escuela' => $escuela])
            ->download('formato-solicitud.pdf');
    }
}
