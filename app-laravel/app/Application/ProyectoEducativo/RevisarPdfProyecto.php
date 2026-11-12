<?php

namespace App\Application\ProyectoEducativo;

use App\Infrastructure\Documentos\ContadorPaginasPdf;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Revisión de un PDF del Proyecto Educativo (PAEC y planeación de la formación continua): tamaño, que de verdad sea un PDF,
 * que se pueda leer y que tenga de 1 a 2 páginas. Los mensajes son para el solicitante.
 */
class RevisarPdfProyecto
{
    public function __construct(private readonly ContadorPaginasPdf $contador) {}

    /**
     * @return int páginas del PDF
     *
     * @throws RuntimeException con el mensaje para el solicitante
     */
    public function revisar(UploadedFile $archivo): int
    {
        if ((int) $archivo->getSize() > ReglasPaecProyecto::MAX_MB * 1024 * 1024) {
            throw new RuntimeException('El archivo no debe pesar más de '.ReglasPaecProyecto::MAX_MB.' MB.');
        }

        // El nombre y el tipo MIME los manda el cliente: se confía en los primeros bytes (todo PDF empieza con %PDF-).
        $ruta = (string) $archivo->getRealPath();
        if (! is_file($ruta) || file_get_contents($ruta, false, null, 0, 5) !== '%PDF-') {
            throw new RuntimeException('Debe ser un archivo PDF.');
        }

        try {
            $paginas = $this->contador->contar($archivo);
        } catch (RuntimeException) {
            throw new RuntimeException('No se pudo leer el PDF. Verifica que no esté dañado ni protegido con contraseña.');
        }

        [$min, $max] = ReglasPaecProyecto::PAGINAS;
        if ($paginas < $min || $paginas > $max) {
            throw new RuntimeException("El PDF debe tener entre {$min} y {$max} páginas (tiene {$paginas}).");
        }

        return $paginas;
    }
}
