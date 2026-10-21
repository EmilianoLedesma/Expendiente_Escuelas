<?php

namespace App\Application\Infraestructura;

/**
 * Única fuente de verdad de "¿esta fila de infraestructura trae dato?" (WS-2
 * item 1). La comparten el alta por nivel y la edición del Paso 3: el
 * componente Livewire no pre-filtra por su cuenta — construye la entrada para
 * todo tipo aplicable y delega la decisión de persistir aquí, para que no
 * puedan divergir dos copias de la misma regla. Un booleano solo cuenta como
 * dato cuando es `true`; de lo contrario un caller de API podría crear filas
 * vacías, y por ADR-005 ese tipo/categoría quedaría "capturado" para siempre
 * sin que el solicitante haya declarado nada.
 */
class FilaSignificativa
{
    /**
     * campoFutbol solo cuenta si trae tipoSuperficie o formato.
     *
     * @param  array{cantidad: int|null, superficieM2: float|null, capacidadPromedio: int|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, destinadoA: string|null, campoFutbol: array{tipoSuperficie: string|null, formato: string|null}|null, materialesBiblioteca: list<array<string, mixed>>}  $espacio
     */
    public static function espacio(array $espacio): bool
    {
        return $espacio['cantidad'] !== null
            || $espacio['superficieM2'] !== null
            || $espacio['capacidadPromedio'] !== null
            || $espacio['destinadoA'] !== null
            || $espacio['ventilacionNatural'] === true
            || $espacio['iluminacionNatural'] === true
            || $espacio['materialesBiblioteca'] !== []
            || ($espacio['campoFutbol'] !== null
                && ($espacio['campoFutbol']['tipoSuperficie'] !== null || $espacio['campoFutbol']['formato'] !== null));
    }

    /**
     * Mismo espíritu que espacio(): un sanitario todo-null no debe crear fila.
     *
     * @param  array{cantidadRetretes: int|null, cantidadMingitorios: int|null, cantidadLavabos: int|null, superficieM2: float|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, cantidadBacinicas: int|null}  $sanitario
     */
    public static function sanitario(array $sanitario): bool
    {
        return $sanitario['cantidadRetretes'] !== null
            || $sanitario['cantidadMingitorios'] !== null
            || $sanitario['cantidadLavabos'] !== null
            || $sanitario['superficieM2'] !== null
            || $sanitario['cantidadBacinicas'] !== null
            || $sanitario['ventilacionNatural'] === true
            || $sanitario['iluminacionNatural'] === true;
    }
}
