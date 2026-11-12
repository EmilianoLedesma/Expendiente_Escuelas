<?php

namespace App\Application\ProyectoEducativo\DTO;

/**
 * Sección 7 del Proyecto Educativo (Currículum Ampliado). Propiedades = columnas = propiedades del componente Livewire.
 * `*_trabaja` llega como 'si' o 'no' (el radio del formulario); la base lo guarda como booleano.
 */
final readonly class DatosCurriculumProyecto
{
    public function __construct(
        public ?string $practica_ciudadana_trabaja = null,
        public ?string $practica_ciudadana_justificacion = null,
        public ?string $salud_trabaja = null,
        public ?string $salud_justificacion = null,
        public ?string $actividad_fisica_trabaja = null,
        public ?string $actividad_fisica_justificacion = null,
        public ?string $sexualidad_genero_trabaja = null,
        public ?string $sexualidad_genero_justificacion = null,
        public ?string $artistico_cultural_trabaja = null,
        public ?string $artistico_cultural_justificacion = null,
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
