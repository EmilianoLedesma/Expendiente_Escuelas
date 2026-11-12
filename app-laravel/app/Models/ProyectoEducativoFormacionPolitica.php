<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sección 11 (Formación Docente): texto de la política global de un tipo de formación ('inicial' o 'continua'). La fila
 * 'continua' también guarda el PDF de planeación (ruta, nombre original y páginas).
 *
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string $tipo
 * @property string|null $politica
 * @property string|null $planeacion_path
 * @property string|null $planeacion_nombre
 * @property int|null $planeacion_paginas
 */
class ProyectoEducativoFormacionPolitica extends Model
{
    protected $table = 'proyecto_educativo_formacion_politica';

    protected $guarded = ['id'];

    /**
     * Rutas en disco de los PDF de planeación de esos niveles, con lock (serializa con un guardado concurrente que reemplace
     * el archivo). Quien borra niveles o trámites las reúne ANTES del borrado: después, el cascade ya se llevó las filas.
     *
     * @param  iterable<int>  $nivelIds
     * @return list<string>
     */
    public static function rutasDe(iterable $nivelIds): array
    {
        return static::whereIn('escuela_nivel_id', $nivelIds)->whereNotNull('planeacion_path')->lockForUpdate()->pluck('planeacion_path')->values()->all();
    }
}
