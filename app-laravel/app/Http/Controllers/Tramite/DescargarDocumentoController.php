<?php

namespace App\Http\Controllers\Tramite;

use App\Application\Documentos\ObtenerDocumentoCapturado;
use App\Models\Escuela;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DescargarDocumentoController
{
    public function __invoke(Escuela $escuela, string $clave, ObtenerDocumentoCapturado $obtener): StreamedResponse
    {
        $ruta = $obtener->ejecutar($escuela->id, $clave);

        abort_if($ruta === null, 404);

        return Storage::disk('documentos')->response($ruta);
    }
}
