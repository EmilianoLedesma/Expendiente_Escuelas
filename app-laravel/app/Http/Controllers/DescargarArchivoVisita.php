<?php

namespace App\Http\Controllers;

use App\Application\Revision\RolesSedeq;
use App\Models\EscuelaNivel;
use App\Models\RespuestaVisita;
use App\Models\RespuestaVisitaArchivo;
use App\Models\VisitaInspeccion;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Acta de una visita y archivos de respuesta del solicitante. Solo el supervisor o el dueño de la escuela (la
 * habilidad `responderVisita` es solo del dueño): `verVisita` admitiría también al responsable por nivel, y la
 * descarga no es suya. La visita y el archivo de la URL deben pertenecer al nivel de la URL (sin IDOR).
 */
class DescargarArchivoVisita
{
    public function acta(EscuelaNivel $escuelaNivel, VisitaInspeccion $visita): StreamedResponse
    {
        $this->autorizar($escuelaNivel);
        abort_unless((int) $visita->escuela_nivel_id === $escuelaNivel->id, 404);

        return $this->servir($visita->acta_path, $visita->acta_nombre, 'acta');
    }

    public function respuesta(EscuelaNivel $escuelaNivel, VisitaInspeccion $visita, RespuestaVisitaArchivo $archivo): StreamedResponse
    {
        $this->autorizar($escuelaNivel);
        abort_unless((int) $visita->escuela_nivel_id === $escuelaNivel->id, 404);
        abort_unless(RespuestaVisita::whereKey($archivo->respuesta_id)->where('visita_id', $visita->id)->exists(), 404);

        return $this->servir($archivo->archivo_path, $archivo->archivo_nombre, 'respuesta');
    }

    private function autorizar(EscuelaNivel $escuelaNivel): void
    {
        $usuario = auth()->user();
        // verVisita para el supervisor exige además que el nivel sea visible a SEDEQ (nunca un borrador en captura).
        abort_unless(($usuario->hasRole(RolesSedeq::SUPERVISOR) && Gate::allows('verVisita', $escuelaNivel)) || Gate::allows('responderVisita', $escuelaNivel), 403);
    }

    private function servir(?string $ruta, ?string $nombre, string $respaldo): StreamedResponse
    {
        $disco = Storage::disk('documentos');
        abort_if($ruta === null || ! $disco->exists($ruta), 404);

        // Solo se guardan PDF: se sirve como PDF, sin que el navegador adivine otro tipo y como descarga.
        return $disco->response($ruta, $this->nombreSeguro($nombre, $respaldo), ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'], 'attachment');
    }

    /** El nombre es dato del cliente: sin separadores de ruta, control, comillas ni puntos, y siempre .pdf. */
    private function nombreSeguro(?string $nombre, string $respaldo): string
    {
        $limpio = preg_replace('/[\x00-\x1F\x7F\/\\\\"\'<>:*?|;%]+/u', '', (string) $nombre) ?? '';
        $base = str_replace('.', '', pathinfo($limpio, PATHINFO_FILENAME));

        return (trim($base) === '' ? $respaldo : trim($base)).'.pdf';
    }
}
