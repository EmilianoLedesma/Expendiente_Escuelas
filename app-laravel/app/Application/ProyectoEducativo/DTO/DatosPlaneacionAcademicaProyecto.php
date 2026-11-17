<?php

namespace App\Application\ProyectoEducativo\DTO;

use Illuminate\Http\UploadedFile;

/**
 * Sección 17 del Proyecto Educativo (Planeación Académica). Propiedades = propiedades del componente Livewire. Un archivo
 * en null significa "conservar el ya guardado" (si no hay ninguno guardado, es un error de requerido).
 */
final readonly class DatosPlaneacionAcademicaProyecto
{
    public function __construct(
        public ?UploadedFile $archivo_planeacion = null,
        public ?string $descripcion_planeacion = null,
    ) {}

    /** Nombres de los campos, tomados del constructor para que no puedan desfasarse. @return list<string> */
    public static function campos(): array
    {
        return array_map(fn (\ReflectionParameter $p) => $p->getName(), (new \ReflectionClass(self::class))->getConstructor()?->getParameters() ?? []);
    }
}
