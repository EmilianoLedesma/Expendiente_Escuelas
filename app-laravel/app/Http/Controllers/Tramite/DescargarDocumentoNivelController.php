<?php

namespace App\Http\Controllers\Tramite;

use App\Application\Documentos\ObtenerDocumentoCapturado;
use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DescargarDocumentoNivelController
{
    public function __invoke(EscuelaNivel $escuelaNivel, string $clave, ObtenerDocumentoCapturado $obtener): StreamedResponse
    {
        $ruta = $obtener->paraEscuelaNivel($escuelaNivel->id, $clave);

        $disco = Storage::disk('documentos');

        abort_if($ruta === null || ! $disco->exists($ruta), 404);

        return $disco->response($ruta);
    }
}
