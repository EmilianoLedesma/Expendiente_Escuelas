<?php

namespace App\Application\Validaciones\DTO;

use DateTimeInterface;

final readonly class ValidacionFinal
{
    /**
     * @param  list<FilaValidacion>  $filas  escuela/plantel documents (Paso 2.2)
     * @param  list<SeccionNivel>  $niveles  each escuela_nivel's documents (Paso 2.4)
     */
    public function __construct(
        public int $evaluacionId,
        public DateTimeInterface $generadaEn,
        public bool $listaParaEnvio,
        public array $filas,
        public array $niveles = [],
    ) {}

    /** @return list<FilaValidacion> */
    public function conEstado(string $estado): array
    {
        return array_values(array_filter($this->filas, fn (FilaValidacion $f) => $f->estado === $estado));
    }

    /** @return list<FilaValidacion> across the escuela section and every level. */
    public function totalConEstado(string $estado): array
    {
        return array_merge($this->conEstado($estado), ...array_map(fn (SeccionNivel $s) => $s->conEstado($estado), $this->niveles));
    }
}
