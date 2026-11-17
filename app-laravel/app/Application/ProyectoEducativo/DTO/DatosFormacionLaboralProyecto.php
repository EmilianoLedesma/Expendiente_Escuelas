<?php

namespace App\Application\ProyectoEducativo\DTO;

/** Sección 14 del Proyecto Educativo (Plan de Estudios, Formación Laboral). Propiedades = columnas = propiedades del componente Livewire. */
final readonly class DatosFormacionLaboralProyecto
{
    public function __construct(
        public ?string $formacion_laboral_1 = null,
        public ?string $formacion_laboral_2 = null,
        public ?string $otras_formaciones = null,
    ) {}

    /** Nombres de los campos, tomados del constructor para que no puedan desfasarse. @return list<string> */
    public static function campos(): array
    {
        return array_map(fn (\ReflectionParameter $p) => $p->getName(), (new \ReflectionClass(self::class))->getConstructor()?->getParameters() ?? []);
    }

    /** @return array<string, string|null> */
    public function aArray(): array
    {
        return get_object_vars($this);
    }
}
