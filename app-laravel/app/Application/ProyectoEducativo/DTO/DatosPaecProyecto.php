<?php

namespace App\Application\ProyectoEducativo\DTO;

use Illuminate\Http\UploadedFile;

/**
 * Sección 6 del Proyecto Educativo (PAEC). Propiedades = propiedades del componente Livewire. Un archivo en null significa
 * "conservar el ya guardado" (si no hay ninguno guardado, es un error de requerido).
 */
final readonly class DatosPaecProyecto
{
    public function __construct(
        public ?string $diagnostico = null,
        public ?UploadedFile $hoja_ruta = null,
        public ?UploadedFile $planeacion = null,
    ) {}

    /** Nombres de los campos, tomados del constructor para que no puedan desfasarse. @return list<string> */
    public static function campos(): array
    {
        return array_map(fn (\ReflectionParameter $p) => $p->getName(), (new \ReflectionClass(self::class))->getConstructor()?->getParameters() ?? []);
    }
}
