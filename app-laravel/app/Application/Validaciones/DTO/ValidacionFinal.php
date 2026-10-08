<?php

namespace App\Application\Validaciones\DTO;

use DateTimeInterface;

final readonly class ValidacionFinal
{
    /**
     * @param  int|null  $evaluacionId  null until EjecutarValidacionFinal::guardar() stores the evaluation
     * @param  list<FilaValidacion>  $filas  escuela/plantel documents (Paso 2.2)
     * @param  list<SeccionCapacidad>  $capacidad  capacity engine per level; no_cumple and no_evaluable (missing capture) block (WS-7a)
     * @param  list<SeccionNivel>  $niveles  each escuela_nivel's documents (Paso 2.4)
     */
    public function __construct(
        public ?int $evaluacionId,
        public DateTimeInterface $generadaEn,
        public bool $listaParaEnvio,
        public array $filas,
        public array $capacidad = [],
        public array $niveles = [],
    ) {}

    /**
     * The ready rule (ADR-007 + WS-7a §3), one line per blocking result: documental
     * no_cumple (escuela and every level), capacity no_cumple, capacity no_evaluable
     * (a capture is missing). Alerts, documental no_evaluable and capacity
     * no_verificable never block.
     *
     * @param  list<FilaValidacion>  $filas
     * @param  list<SeccionCapacidad>  $capacidad
     * @param  list<SeccionNivel>  $niveles
     * @return list<string>
     */
    public static function bloqueantes(array $filas, array $capacidad, array $niveles): array
    {
        $motivos = [];

        foreach ($filas as $fila) {
            if ($fila->estado === 'no_cumple') {
                $motivos[] = $fila->titulo;
            }
        }

        foreach ($niveles as $seccion) {
            foreach ($seccion->conEstado('no_cumple') as $fila) {
                $motivos[] = "{$seccion->nivel}: {$fila->titulo}";
            }
        }

        foreach ($capacidad as $seccion) {
            foreach ([...$seccion->conEstado('no_cumple'), ...$seccion->conEstado('no_evaluable')] as $fila) {
                $motivos[] = "Capacidad instalada · {$seccion->nivel}: {$fila->titulo}";
            }
        }

        return $motivos;
    }

    /** @return list<string> */
    public function motivosBloqueo(): array
    {
        return self::bloqueantes($this->filas, $this->capacidad, $this->niveles);
    }

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
