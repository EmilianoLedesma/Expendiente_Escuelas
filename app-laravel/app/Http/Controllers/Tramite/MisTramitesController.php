<?php

namespace App\Http\Controllers\Tramite;

use App\Application\Tramite\ListarTramitesDelSolicitante;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MisTramitesController
{
    public function __invoke(Request $request, ListarTramitesDelSolicitante $listar): View
    {
        // Un usuario sin fila en solicitantes (sedeq, legado) ve el estado vacío, no un 500.
        $solicitante = $request->user()->solicitante;

        return view('tramite.index', [
            'tramites' => $solicitante === null ? [] : $listar->ejecutar($solicitante->getKey()),
        ]);
    }
}
