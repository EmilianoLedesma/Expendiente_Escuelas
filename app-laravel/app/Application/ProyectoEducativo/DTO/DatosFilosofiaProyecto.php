<?php

namespace App\Application\ProyectoEducativo\DTO;

/** Sección 2 del Proyecto Educativo (Filosofía Institucional). Propiedades = columnas = propiedades del componente Livewire. */
final readonly class DatosFilosofiaProyecto
{
    public function __construct(
        public ?string $mision = null,
        public ?string $vision = null,
        public ?string $valores = null,
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
