<?php

namespace App\Application\ProyectoEducativo\DTO;

/** Sección 10 del Proyecto Educativo (Perfil Docente). Propiedades = columnas = propiedades del componente Livewire. */
final readonly class DatosPerfilDocenteProyecto
{
    public function __construct(
        public ?string $habilidades_virtudes = null,
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
