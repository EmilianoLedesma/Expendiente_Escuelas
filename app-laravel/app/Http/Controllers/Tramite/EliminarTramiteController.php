<?php

namespace App\Http\Controllers\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EliminarTramite;
use App\Models\Escuela;
use Illuminate\Http\RedirectResponse;

class EliminarTramiteController
{
    public function __invoke(Escuela $escuela, EliminarTramite $eliminar): RedirectResponse
    {
        try {
            $eliminar->ejecutar($escuela->id);
        } catch (PrecondicionIncumplida $e) {
            return redirect()->route('tramite.index')->with('error', $e->getMessage());
        }

        return redirect()->route('tramite.index')->with('status', 'Trámite eliminado.');
    }
}
