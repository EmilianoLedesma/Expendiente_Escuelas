<?php

namespace App\Application\ProyectoEducativo\DTO;

/**
 * Sección 20 del Proyecto Educativo (Bibliografía del Proyecto). La propiedad es la del componente Livewire: la lista de
 * referencias tal como llega del navegador (cada una con autores, anio, titulo, fuente y url). Puede traer cualquier cosa:
 * ReglasBibliografiaProyecto::normalizar() la convierte en una lista de textos.
 */
final readonly class DatosBibliografiaProyecto
{
    /** @param  array<array-key, mixed>  $referencias */
    public function __construct(
        public array $referencias = [],
    ) {}

    /** Nombres de los campos, tomados del constructor para que no puedan desfasarse. @return list<string> */
    public static function campos(): array
    {
        return array_map(fn (\ReflectionParameter $p) => $p->getName(), (new \ReflectionClass(self::class))->getConstructor()?->getParameters() ?? []);
    }
}
