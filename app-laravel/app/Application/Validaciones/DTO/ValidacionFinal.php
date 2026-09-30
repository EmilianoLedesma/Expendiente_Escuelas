<?php

namespace App\Application\Validaciones\DTO;

use DateTimeInterface;

final readonly class ValidacionFinal
{
    /**
     * @param  list<FilaValidacion>  $filas  documental validation (decides listaParaEnvio)
     * @param  list<SeccionCapacidad>  $capacidad  capacity engine per level (observations, never blocks)
     */
    public function __construct(
        public int $evaluacionId,
        public DateTimeInterface $generadaEn,
        public bool $listaParaEnvio,
        public array $filas,
        public array $capacidad = [],
    ) {}

    /** @return list<FilaValidacion> */
    public function conEstado(string $estado): array
    {
        return array_values(array_filter($this->filas, fn (FilaValidacion $f) => $f->estado === $estado));
    }
}
