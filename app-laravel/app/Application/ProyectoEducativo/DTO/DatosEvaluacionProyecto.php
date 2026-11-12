<?php

namespace App\Application\ProyectoEducativo\DTO;

/** Sección 5 del Proyecto Educativo (Modelo de Evaluación). Propiedades = columnas = propiedades del componente Livewire. */
final readonly class DatosEvaluacionProyecto
{
    public function __construct(
        public ?string $evaluacion_diagnostica = null,
        public ?string $evaluacion_formativa = null,
        public ?string $evaluacion_sumativa = null,
        public ?string $heteroevaluacion = null,
        public ?string $coevaluacion = null,
        public ?string $autoevaluacion = null,
        public ?string $elementos_porcentajes = null,
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
