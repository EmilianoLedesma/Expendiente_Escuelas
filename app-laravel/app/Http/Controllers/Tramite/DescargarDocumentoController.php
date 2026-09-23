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

        $disco = Storage::disk('documentos');

        abort_if($ruta === null || ! $disco->exists($ruta), 404);

        return $disco->response($ruta);
    }
}
