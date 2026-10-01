<?php

namespace App\Domain\Validaciones\Engine;

/**
 * Every magnitude and declared value the capacity rules of one escuela_nivel
 * read. null always means "not captured" and makes the rules that need it
 * no_evaluable — never a silent zero. Built by
 * App\Application\Validaciones\ConstruirDatosCapacidad.
 */
final readonly class DatosCapacidadNivel
{
    /**
     * @param  array<string, int>|null  $matriculaPorSala  sala clave => alumnos (Inicial)
     * @param  array<int, int>|null  $personalPorCargo  cargo_puesto_id => complete personal rows; null = plantilla not captured
     * @param  array<int, array<string, int>>  $personalPorCargoYSala  cargo_puesto_id => sala clave => count
     * @param  list<LineaMobiliario>  $mobiliario
     */
    public function __construct(
        public ?int $matriculaNivel = null,
        public ?int $matriculaPlantel = null,
        public ?array $matriculaPorSala = null,
        public ?int $gradosOfertados = null,
        public ?int $numeroAulas = null,
        public ?float $superficieAulas = null,
        public ?float $superficiePredio = null,
        public ?float $superficieConstruida = null,
        public ?float $superficieRecreativa = null,
        public ?float $superficieUsosMultiples = null,
        public ?float $superficieSanitariosAlumnos = null,
        public ?int $acervoTitulos = null,
        public ?array $personalPorCargo = null,
        public array $personalPorCargoYSala = [],
        public ?int $docentesEducacionFisica = null,
        public array $mobiliario = [],
    ) {}
}
