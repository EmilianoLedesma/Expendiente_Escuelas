<?php

namespace App\Http\Controllers\Tramite;

use App\Infrastructure\Pdf\AnexoInstalacionesPdf;
use App\Models\EscuelaNivel;
use Illuminate\Http\Response;

/** Anexo 2 Instalaciones del nivel: solo lectura, siempre disponible como borrador (D4). Autoriza la ruta (`can:descargarAnexo`). */
class AnexoInstalacionesPdfController
{
    public function __invoke(EscuelaNivel $escuelaNivel, AnexoInstalacionesPdf $pdf): Response
    {
        return $pdf->generar($escuelaNivel);
    }
}
