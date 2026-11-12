<?php

namespace App\Infrastructure\Documentos;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

/** Cuenta las páginas reales de un PDF (smalot/pdfparser, PHP puro: lee también PDF con objetos comprimidos). */
class ContadorPaginasPdf
{
    /** @throws RuntimeException si el archivo no se puede leer como PDF o no tiene páginas */
    public function contar(UploadedFile $archivo): int
    {
        try {
            $paginas = count((new Parser)->parseFile((string) $archivo->getRealPath())->getPages());
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo leer el PDF.', previous: $e);
        }

        return $paginas > 0 ? $paginas : throw new RuntimeException('El PDF no tiene páginas.');
    }
}
