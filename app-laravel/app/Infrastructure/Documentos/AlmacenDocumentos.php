<?php

namespace App\Infrastructure\Documentos;

use Illuminate\Http\UploadedFile;

interface AlmacenDocumentos
{
    public function guardar(string $ambito, int $ownerId, string $clave, UploadedFile $archivo): string;

    public function eliminar(string $path): void;
}
