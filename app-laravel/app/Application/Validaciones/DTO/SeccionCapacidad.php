<?php

namespace App\Application\Validaciones\DTO;

/** Capacity engine results for one escuela_nivel, as shown and stored. */
final readonly class SeccionCapacidad
{
    /** @param list<FilaValidacion> $filas */
    public function __construct(
        public int $escuelaNivelId,
        public string $nivel,
        public array $filas,
    ) {}

    /** @return list<FilaValidacion> */
    public function conEstado(string $estado): array
    {
        return array_values(array_filter($this->filas, fn (FilaValidacion $f) => $f->estado === $estado));
    }

    /** @return array{escuelaNivelId: int, nivel: string, filas: list<array<string, mixed>>} */
    public function aArreglo(): array
    {
        return [
            'escuelaNivelId' => $this->escuelaNivelId,
            'nivel' => $this->nivel,
            'filas' => array_map(fn (FilaValidacion $f) => $f->aArreglo(), $this->filas),
        ];
    }

    /** @param array{escuelaNivelId: int, nivel: string, filas: list<array{clave: string, titulo: string, estado: string, mensaje: string, documentos: array<string, string>, lineas: list<string>, pasoCorreccion?: string|null}>} $seccion */
    public static function desdeArreglo(array $seccion): self
    {
        return new self($seccion['escuelaNivelId'], $seccion['nivel'], array_map(fn (array $f) => FilaValidacion::desdeArreglo($f), $seccion['filas']));
    }
}
