<?php

namespace App\Application\ProyectoEducativo\DTO;

/** Sección 3 del Proyecto Educativo (Objetivos Institucionales). Propiedades = columnas = propiedades del componente Livewire. */
final readonly class DatosObjetivosProyecto
{
    public function __construct(
        public ?string $objetivo_general = null,
        public ?string $objetivos_especificos = null,
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
