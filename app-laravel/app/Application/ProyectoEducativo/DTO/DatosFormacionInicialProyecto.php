<?php

namespace App\Application\ProyectoEducativo\DTO;

/**
 * Sección 11 del Proyecto Educativo, bloque "Formación Docente Inicial": la política y la lista de acciones. Cada acción
 * es un arreglo con las claves de ReglasFormacionProyecto::CAMPOS_ACCION (horas "HH:MM", fechas "AAAA-MM-DD", días como lista).
 */
final readonly class DatosFormacionInicialProyecto
{
    /** @param  array<array-key, mixed>  $acciones */
    public function __construct(
        public ?string $politica_inicial = null,
        public array $acciones = [],
    ) {}
}
