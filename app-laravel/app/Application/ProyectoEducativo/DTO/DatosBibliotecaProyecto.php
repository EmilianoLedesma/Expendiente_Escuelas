<?php

namespace App\Application\ProyectoEducativo\DTO;

/**
 * Sección 19 del Proyecto Educativo (Servicios de Biblioteca y Acervo Bibliográfico). Propiedades = columnas = propiedades
 * del componente Livewire. Las respuestas Sí/No viajan como 'si' o 'no'. Los dos bloques se guardan por separado: cada
 * guardado usa solo los campos de su bloque.
 */
final readonly class DatosBibliotecaProyecto
{
    public function __construct(
        public ?string $biblioteca_tipo = null,
        public ?string $biblioteca_virtual_tipo = null,
        public ?string $biblioteca_tiene_encargado = null,
        public ?string $biblioteca_perfil_encargado = null,
        public ?string $biblioteca_prestamo = null,
        public ?string $biblioteca_tiene_programa_lectura = null,
        public ?string $biblioteca_programa_lectura = null,
        public ?string $biblioteca_otros_programas = null,
        public ?string $acervo_clasificacion = null,
        public ?string $acervo_titulos_basicos = null,
        public ?string $acervo_titulos_lectura = null,
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
