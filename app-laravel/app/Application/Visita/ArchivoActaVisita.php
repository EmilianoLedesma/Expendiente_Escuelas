<?php

namespace App\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\ProyectoEducativo\RevisarPdfProyecto;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/** Revisión y almacenamiento del PDF del acta de una visita (RegistrarActaVisita, AdjuntarActaVisita y los adjuntos de ResponderObservaciones). */
class ArchivoActaVisita
{
    public function __construct(
        private readonly AlmacenDocumentos $almacen,
        private readonly RevisarPdfProyecto $revisor,
    ) {}

    /** PDF de hasta 10 MB comprobado por su contenido, sin tope de páginas. */
    public function revisar(UploadedFile $acta, string $campo = 'acta'): void
    {
        try {
            $this->revisor->revisar($acta, null);
        } catch (RuntimeException $e) {
            throw new DatosInvalidos([$campo => $e->getMessage()]);
        }
    }

    /** @return string ruta en disco, bajo el ámbito de la escuela */
    public function guardar(int $escuelaId, UploadedFile $acta, string $clave = 'acta'): string
    {
        return $this->almacen->guardar('visitas', $escuelaId, $clave, $acta);
    }

    /** Un fallo de disco se reporta, nunca revierte ni interrumpe. */
    public function borrar(string $ruta): void
    {
        rescue(fn () => $this->almacen->eliminar($ruta), report: true);
    }
}
