<?php

namespace App\Http\Controllers\Tramite;

use App\Application\Archivo\ArchivarTramite;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Models\Escuela;
use Illuminate\Http\RedirectResponse;

/** DELETE /tramite/{escuela} (nombre de ruta histórico): el dueño archiva su trámite, nada se borra. */
class EliminarTramiteController
{
    public function __invoke(Escuela $escuela, ArchivarTramite $archivar): RedirectResponse
    {
        try {
            $archivar->ejecutar($escuela->id, (int) auth()->id());
        } catch (PrecondicionIncumplida $e) {
            return redirect()->route('tramite.index')->with('toast', ['tipo' => 'error', 'titulo' => 'No se pudo archivar el trámite', 'descripcion' => $e->getMessage()]);
        }

        return redirect()->route('tramite.index')->with('toast', ['tipo' => 'success', 'titulo' => 'Trámite archivado', 'descripcion' => 'Ya no aparece en tus trámites. Para recuperarlo, contacta a SEDEQ.']);
    }
}
