<?php

namespace App\Http\Controllers\Tramite;

use App\Application\ResponsablesNivel\ListarNivelesDelResponsable;
use App\Application\Tramite\ListarTramitesDelSolicitante;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MisTramitesController
{
    public function __invoke(Request $request, ListarTramitesDelSolicitante $listar, ListarNivelesDelResponsable $listarNiveles): View
    {
        $usuario = $request->user();
        $solicitante = $usuario->solicitante;

        if ($solicitante === null && ($niveles = $listarNiveles->ejecutar($usuario->id)) !== []) {
            return view('tramite.mis-niveles', ['niveles' => $niveles]);
        }

        // Un usuario sin fila en solicitantes (sedeq, legado) ve el estado vacío, no un 500.
        return view('tramite.index', [
            'tramites' => $solicitante === null ? [] : $listar->ejecutar($solicitante->getKey()),
            'puedeIniciar' => $solicitante !== null,
        ]);
    }
}
