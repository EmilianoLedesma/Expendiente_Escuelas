<?php

namespace Tests\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;

/** PDFs reales (dompdf) con un número exacto de páginas, para probar el conteo de páginas sin mocks. */
trait GeneraPdfs
{
    protected function pdfDe(int $paginas): string
    {
        $html = '';
        for ($i = 1; $i <= $paginas; $i++) {
            $html .= '<div style="'.($i < $paginas ? 'page-break-after: always;' : '').'">Página '.$i.'</div>';
        }

        return Pdf::loadHTML($html)->output();
    }

    /** @param int $relleno bytes de espacio al final (el PDF sigue siendo válido) para probar el tope de tamaño */
    protected function archivoPdf(int $paginas, string $nombre = 'documento.pdf', int $relleno = 0): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $this->pdfDe($paginas).str_repeat(' ', $relleno));
    }
}
