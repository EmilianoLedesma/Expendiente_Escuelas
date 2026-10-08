<?php

namespace App\Http\Controllers\Tramite;

use App\Application\Tramite\ResumenTramite;
use App\Models\Escuela;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ResumenTramiteController
{
    public function __invoke(Request $request, Escuela $escuela, ResumenTramite $resumenTramite): View
    {
        return view('tramite.resumen', [
            'resumen' => $resumenTramite->paraEscuela($escuela->id, $request->user()->id),
            // El reporte enviado se descarga por `view` (hoy solo el dueño): a un responsable no se le muestra un enlace que daría 403.
            'puedeDescargarReporte' => $request->user()->can('view', $escuela),
        ]);
    }
}
