<?php

namespace App\Http\Controllers\Tramite;

use App\Application\Tramite\EstadoPaso24;
use App\Infrastructure\Pdf\FormatoSolicitudPdf;
use App\Models\EscuelaNivel;
use Illuminate\Http\Response;

/**
 * Sin caso de uso de Application a propósito: no hay persistencia, solo el
 * renderizado de solo lectura que ya vive en Infrastructure/Pdf. La única
 * decisión (¿ya hay turno y tipo de alumnado que imprimir?) es de EstadoPaso24.
 */
class FormatoSolicitudPdfController
{
    public function __invoke(EscuelaNivel $escuelaNivel, EstadoPaso24 $estadoPaso24, FormatoSolicitudPdf $pdf): Response
    {
        abort_unless($estadoPaso24->datosNivelCapturados($escuelaNivel->id), 404);

        return $pdf->generar($escuelaNivel);
    }
}
