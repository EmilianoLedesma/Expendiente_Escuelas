<?php

namespace App\Application\Infraestructura\DTO;

/**
 * Entrada de EditarInfraestructuraCompartida (WS-7b-2). Las filas tienen la misma
 * forma que DatosInfraestructuraNivel, para que FilaSignificativa y las reglas de
 * campo reciban exactamente lo mismo en el alta y en la edición. Los materiales de
 * biblioteca, el campo de futbol y las bacinicas viajan dentro de su fila.
 */
final readonly class DatosEdicionInfraestructura
{
    /**
     * @param  list<array{tipoEspacioId: int, cantidad: int|null, superficieM2: float|null, capacidadPromedio: int|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, destinadoA: string|null, campoFutbol: array{tipoSuperficie: string|null, formato: string|null}|null, materialesBiblioteca: list<array{tipoMaterialId: int, numeroTitulos: int|null, numeroVolumenes: int|null}>}>  $espacios
     * @param  list<array{categoria: string, cantidadRetretes: int|null, cantidadMingitorios: int|null, cantidadLavabos: int|null, superficieM2: float|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, cantidadBacinicas: int|null}>  $sanitarios
     * @param  list<int>  $quitarEspacios  tipo_espacio_id de las filas a borrar con sus hijos
     * @param  list<string>  $quitarSanitarios  categorías de las filas a borrar con sus bacinicas
     */
    public function __construct(
        public array $espacios,
        public array $sanitarios,
        public array $quitarEspacios = [],
        public array $quitarSanitarios = [],
    ) {}
}
