<?php

namespace App\Application\ProyectoEducativo\DTO;

use Illuminate\Http\UploadedFile;

/**
 * Sección 15 del Proyecto Educativo (Mapa Curricular). Propiedades = propiedades del componente Livewire. Un archivo en
 * null significa "conservar el ya guardado" (si no hay ninguno guardado, es un error de requerido).
 */
final readonly class DatosMapaCurricularProyecto
{
    public function __construct(
        public ?UploadedFile $archivo_mapa = null,
        public ?string $descripcion_mapa = null,
    ) {}

    /** Nombres de los campos, tomados del constructor para que no puedan desfasarse. @return list<string> */
    public static function campos(): array
    {
        return array_map(fn (\ReflectionParameter $p) => $p->getName(), (new \ReflectionClass(self::class))->getConstructor()?->getParameters() ?? []);
    }
}
