<?php

namespace App\Application\Validaciones\DTO;

use DateTimeInterface;

final readonly class ValidacionFinal
{
    /** @param list<FilaValidacion> $filas */
    public function __construct(
        public int $evaluacionId,
        public DateTimeInterface $generadaEn,
        public bool $listaParaEnvio,
        public array $filas,
    ) {}

    /** @return list<FilaValidacion> */
    public function conEstado(string $estado): array
    {
        return array_values(array_filter($this->filas, fn (FilaValidacion $f) => $f->estado === $estado));
    }
}
