<?php

namespace App\Infrastructure\Documentos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Decisión de MVP (docs/superpowers/specs/2026-09-10-paso2-documentos-design.md
 * §10): disco local, nunca dentro de public/. Un almacenamiento externo
 * (S3, etc.) queda considerado para el futuro, no para este MVP — cambiar
 * de disco es una edición de config, no un rediseño de este caso de uso.
 */
class AlmacenDocumentosLocal implements AlmacenDocumentos
{
    public function guardar(string $ambito, int $ownerId, string $clave, UploadedFile $archivo): string
    {
        $ruta = "{$ambito}/{$ownerId}/{$clave}.pdf";

        Storage::disk('documentos')->put($ruta, file_get_contents($archivo->getRealPath()));

        return $ruta;
    }

    public function eliminar(string $path): void
    {
        Storage::disk('documentos')->delete($path);
    }
}
