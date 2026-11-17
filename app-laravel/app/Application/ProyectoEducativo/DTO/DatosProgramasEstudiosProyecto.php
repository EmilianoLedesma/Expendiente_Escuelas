<?php

namespace App\Application\ProyectoEducativo\DTO;

use Illuminate\Http\UploadedFile;

/**
 * Sección 16 del Proyecto Educativo (Programas de Estudios). Propiedades = propiedades del componente Livewire.
 * `retoma_dgb` es 'si' o 'no'. Con 'no', un archivo en null significa "conservar el ya guardado" (si no hay ninguno
 * guardado, es un error de requerido); con 'si', justificación y archivo no aplican y se ignoran.
 */
final readonly class DatosProgramasEstudiosProyecto
{
    public function __construct(
        public ?string $periodicidad_ciclo = null,
        public ?string $periodicidad_otra = null,
        public ?string $modalidad_programas = null,
        public ?string $retoma_dgb = null,
        public ?string $justificacion_adaptacion = null,
        public ?UploadedFile $archivo_programas = null,
    ) {}

    /** Nombres de los campos, tomados del constructor para que no puedan desfasarse. @return list<string> */
    public static function campos(): array
    {
        return array_map(fn (\ReflectionParameter $p) => $p->getName(), (new \ReflectionClass(self::class))->getConstructor()?->getParameters() ?? []);
    }
}
