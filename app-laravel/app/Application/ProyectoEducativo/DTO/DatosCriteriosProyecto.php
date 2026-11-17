<?php

namespace App\Application\ProyectoEducativo\DTO;

/** Sección 13 del Proyecto Educativo (Criterios de Acreditación, Promoción y Certificación). Propiedades = columnas = propiedades del componente Livewire. */
final readonly class DatosCriteriosProyecto
{
    public function __construct(
        public ?string $criterios_acreditacion = null,
        public ?string $opciones_acreditacion = null,
        public ?string $criterios_promocion = null,
        public ?string $criterios_titulacion = null,
        public ?string $servicios_control_escolar = null,
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
