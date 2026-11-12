<?php

namespace App\Application\ProyectoEducativo\DTO;

use Illuminate\Http\UploadedFile;

/**
 * Sección 11 del Proyecto Educativo, bloque "Formación Docente Continua": la política, el PDF de planeación (null = conservar
 * el ya guardado) y la lista de acciones (mismas claves que en el bloque inicial).
 */
final readonly class DatosFormacionContinuaProyecto
{
    /** @param  array<array-key, mixed>  $acciones_continua */
    public function __construct(
        public ?string $politica_continua = null,
        public ?UploadedFile $planeacion_continua = null,
        public array $acciones_continua = [],
    ) {}
}
