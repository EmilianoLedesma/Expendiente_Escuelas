<?php

namespace App\Http\Controllers\Tramite;

use App\Application\Validaciones\ReporteValidacionGuardado;
use App\Models\Escuela;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reporte con que se envió el trámite, para SEDEQ. La ruta exige `verReporteEnviado` (todos los niveles visibles). */
class DescargarReporteEnviadoController
{
    public function __invoke(Escuela $escuela, ReporteValidacionGuardado $reporte): StreamedResponse
    {
        $ruta = $reporte->rutaEnviada($escuela->id);
        $disco = Storage::disk('documentos');

        abort_if($ruta === null || ! $disco->exists($ruta), 404);

        return $disco->response($ruta, 'reporte-validacion-enviado.pdf', ['Content-Type' => 'application/pdf']);
    }
}
