<?php

namespace App\Http\Controllers\Sedeq;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Revision\ArchivoRevisionProyecto;
use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** PDF del proyecto educativo para quien lo revisa. El acceso lo impone el caso de uso (AuthorizationException => 403). */
class DescargarArchivoProyectoController
{
    public function __invoke(EscuelaNivel $escuelaNivel, string $clave, ArchivoRevisionProyecto $archivos): StreamedResponse
    {
        try {
            $ruta = $archivos->ruta((int) auth()->id(), $escuelaNivel->id, $clave);
        } catch (DatosInvalidos) {
            abort(404);
        }

        $disco = Storage::disk('documentos');

        abort_if($ruta === null || ! $disco->exists($ruta), 404);

        // Solo se guardan PDF: se sirve como PDF y sin que el navegador adivine otro tipo.
        return $disco->response($ruta, null, ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff']);
    }
}
