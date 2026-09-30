<?php

namespace App\Infrastructure\Pdf;

use App\Models\EscuelaNivel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Renderizado bajo demanda desde datos ya capturados — no persiste nada, es
 * una respuesta de descarga (docs/superpowers/specs/2026-09-10-paso2-documentos-design.md §7).
 * Desde WS-5b es por nivel (D1b): imprime nivel, turno y tipo de alumnado.
 * La estructura oficial del formato llega con WS-5c.
 */
class FormatoSolicitudPdf
{
    public function generar(EscuelaNivel $escuelaNivel): Response
    {
        $escuelaNivel->load(['nivelEducativo', 'escuela.responsableLegal.personaFisica', 'escuela.responsableLegal.personaMoral', 'escuela.ternasNombres']);

        return Pdf::loadView('pdf.formato-solicitud', ['escuela' => $escuelaNivel->escuela, 'escuelaNivel' => $escuelaNivel])
            ->download("formato-solicitud-{$escuelaNivel->nivelEducativo->clave}.pdf");
    }
}
