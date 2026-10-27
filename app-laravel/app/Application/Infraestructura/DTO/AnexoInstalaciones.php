<?php

namespace App\Application\Infraestructura\DTO;

/**
 * Lo que imprime el Anexo 2 Instalaciones de un nivel. Los nulos son "sin dato": nunca se
 * inventa un NO para lo que no se capturó; `capturado` distingue la línea con fila guardada.
 *
 * `secciones` va por clave (administrativo, cubiculos, recreativo_deportivo, especial, otros)
 * y solo trae las que tienen líneas. "otros" junta lo capturado en un tipo que ya no aplica al nivel.
 */
final readonly class AnexoInstalaciones
{
    /**
     * @param  array{calle: string, numeroExt: string|null, numeroInt: string|null, colonia: string, localidad: string|null, municipio: string, codigoPostal: string, telefono: string|null, correoElectronico: string|null}  $datosGenerales
     * @param  list<array{nombre: string, numeroAlumnos: int}>  $estudiosActuales
     * @param  list<array{nombre: string, tipo: string, esPublico: bool|null, distanciaValor: string|null, distanciaUnidad: string|null}>  $serviciosCercanos
     * @param  array<string, array{titulo: string, lineas: list<array{clave: string, nombre: string, capturado: bool, cantidad: int|null, superficieM2: string|null, capacidadPromedio: int|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, destinadoA: string|null, campoFutbol: array{tipoSuperficie: string|null, formato: string|null}|null, materiales: list<array{clave: string, nombre: string, numeroTitulos: int|null, numeroVolumenes: int|null}>}>}>  $secciones
     * @param  list<array{categoria: string, capturado: bool, cantidadRetretes: int|null, cantidadMingitorios: int|null, cantidadLavabos: int|null, superficieM2: string|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, cantidadBacinicas: int|null}>  $sanitarios
     * @param  array{numeroAulas: int, superficieM2: string|null}|null  $aulas
     */
    public function __construct(
        public array $datosGenerales,
        public array $estudiosActuales,
        public array $serviciosCercanos,
        public ?string $metrosPredio,
        public ?string $metrosConstruidos,
        public ?string $areaCivicaM2,
        public ?bool $tieneAstaBandera,
        public array $secciones,
        public array $sanitarios,
        public ?array $aulas,
    ) {}
}
