<?php

namespace App\Application\ProyectoEducativo\DTO;

/** Sección 8 del Proyecto Educativo (Perfil de Ingreso). Propiedades = columnas = propiedades del componente Livewire. */
final readonly class DatosIngresoProyecto
{
    public function __construct(
        public ?string $perfil_ingreso = null,
        public ?string $mecanismo_ingreso = null,
        public ?string $costo_usuario = null,
        public ?string $instrumentos_evaluacion = null,
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
