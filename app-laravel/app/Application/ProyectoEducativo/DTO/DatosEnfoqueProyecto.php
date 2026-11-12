<?php

namespace App\Application\ProyectoEducativo\DTO;

/** Sección 4 del Proyecto Educativo (Enfoque Pedagógico). Propiedades = columnas = propiedades del componente Livewire. */
final readonly class DatosEnfoqueProyecto
{
    /**
     * @param  list<string|null>|null  $estrategias_centrales
     * @param  list<string|null>|null  $actividades_sustantivas
     */
    public function __construct(
        public ?string $propuesta_global = null,
        public ?string $apropiacion_enfoque = null,
        public ?array $estrategias_centrales = null,
        public ?array $actividades_sustantivas = null,
    ) {}

    /** Nombres de los campos, tomados del constructor para que no puedan desfasarse. @return list<string> */
    public static function campos(): array
    {
        return array_map(fn (\ReflectionParameter $p) => $p->getName(), (new \ReflectionClass(self::class))->getConstructor()?->getParameters() ?? []);
    }

    /** @return array<string, string|list<string|null>|null> */
    public function aArray(): array
    {
        return get_object_vars($this);
    }
}
