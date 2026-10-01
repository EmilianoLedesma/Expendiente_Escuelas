<?php

namespace App\Application\Matricula\DTO;

/**
 * Inicial uses `salas` (sala_id => alumnos, matricula_salas); Preescolar to
 * Secundaria use `grupos` (grado + grupo, matricula_grados). PRD: projected
 * capacity for alta_nueva, enrolled students for reincorporación — same
 * numbers, only the wording differs.
 */
final readonly class DatosMatricula
{
    /**
     * @param  array<int, int>  $salas
     * @param  list<array{gradoId: int, grupo: string, alumnos: int}>  $grupos
     */
    public function __construct(
        public array $salas = [],
        public array $grupos = [],
    ) {}
}
