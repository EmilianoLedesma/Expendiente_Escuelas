<?php

namespace App\Application\ProyectoEducativo\DTO;

/** Sección 12 del Proyecto Educativo (Programa de Academias Docentes). Propiedades = columnas = propiedades del componente Livewire. */
final readonly class DatosAcademiasProyecto
{
    public function __construct(
        public ?string $tipo_academia = null,
        public ?string $descripcion_academia = null,
        public ?string $justificacion_academia = null,
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
