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
            return redirect()->route('tramite.index')->with('toast', ['tipo' => 'error', 'titulo' => 'No se pudo eliminar el trámite', 'descripcion' => $e->getMessage()]);
        }

        return redirect()->route('tramite.index')->with('toast', ['tipo' => 'success', 'titulo' => 'Trámite eliminado', 'descripcion' => 'Se borraron sus datos y archivos.']);
    }
}
