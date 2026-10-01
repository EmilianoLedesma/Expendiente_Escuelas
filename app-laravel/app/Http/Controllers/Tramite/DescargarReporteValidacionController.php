<?php

namespace App\Http\Controllers\Tramite;

use App\Application\Validaciones\ReporteValidacionGuardado;
use App\Models\Escuela;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DescargarReporteValidacionController
{
    public function __invoke(Escuela $escuela, int $evaluacion, ReporteValidacionGuardado $reporte): StreamedResponse
    {
        $ruta = $reporte->ruta($escuela->id, $evaluacion);
        $disco = Storage::disk('documentos');

        abort_if($ruta === null || ! $disco->exists($ruta), 404);

        return $disco->response($ruta, 'reporte-validacion.pdf', ['Content-Type' => 'application/pdf']);
    }
}
