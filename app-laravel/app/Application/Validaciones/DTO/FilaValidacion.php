<?php

namespace App\Application\Validaciones\DTO;

/**
 * One rule result as shown to the applicant and printed in the PDF. Plain
 * strings only, so presentation never imports app/Domain (ADR-001).
 */
final readonly class FilaValidacion
{
    /**
     * @param  string  $estado  cumple|advertencia|no_cumple|no_evaluable
     * @param  array<string, string>  $documentos  tipos_documentos.clave => nombre, the uploads to fix
     * @param  list<string>  $lineas  what was compared, one line per value
     */
    public function __construct(
        public string $clave,
        public string $titulo,
        public string $estado,
        public string $mensaje,
        public array $documentos,
        public array $lineas,
    ) {}

    /** @return array{clave: string, titulo: string, estado: string, mensaje: string, documentos: array<string, string>, lineas: list<string>} */
    public function aArreglo(): array
    {
        return [
            'clave' => $this->clave,
            'titulo' => $this->titulo,
            'estado' => $this->estado,
            'mensaje' => $this->mensaje,
            'documentos' => $this->documentos,
            'lineas' => $this->lineas,
        ];
    }

    /** @param array{clave: string, titulo: string, estado: string, mensaje: string, documentos: array<string, string>, lineas: list<string>} $fila */
    public static function desdeArreglo(array $fila): self
    {
        return new self($fila['clave'], $fila['titulo'], $fila['estado'], $fila['mensaje'], $fila['documentos'], $fila['lineas']);
    }
}
