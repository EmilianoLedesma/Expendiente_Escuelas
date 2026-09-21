<?php

namespace App\Application\Infraestructura\DTO;

/**
 * Entrada de RegistrarInfraestructuraNivel. Un solo envío de formulario trae
 * datos de dos ámbitos distintos: espacios y sanitarios son del plantel,
 * las aulas son del escuela_nivel — por eso conviven en un DTO y se
 * escriben en una sola transacción.
 *
 * `campoFutbol` solo se llena para el tipo_espacio con
 * permite_campo_futbol; `materialesBiblioteca` solo para el que tiene
 * permite_material_biblioteca; `cantidadBacinicas` solo para Inicial.
 */
final readonly class DatosInfraestructuraNivel
{
    /**
     * @param  list<array{tipoEspacioId: int, cantidad: int|null, superficieM2: float|null, capacidadPromedio: int|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, destinadoA: string|null, campoFutbol: array{tipoSuperficie: string|null, formato: string|null}|null, materialesBiblioteca: list<array{tipoMaterialId: int, numeroTitulos: int|null, numeroVolumenes: int|null}>}>  $espacios
     * @param  list<array{categoria: string, cantidadRetretes: int|null, cantidadMingitorios: int|null, cantidadLavabos: int|null, superficieM2: float|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, cantidadBacinicas: int|null}>  $sanitarios
     */
    public function __construct(
        public array $espacios,
        public array $sanitarios,
        public int $numeroAulas,
        public ?float $superficieAulasM2 = null,
    ) {}
}
