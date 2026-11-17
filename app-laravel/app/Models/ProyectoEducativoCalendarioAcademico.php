<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $archivo_path
 * @property string|null $archivo_nombre
 * @property int|null $archivo_paginas
 * @property string|null $descripcion
 */
class ProyectoEducativoCalendarioAcademico extends Model
{
    protected $table = 'proyecto_educativo_calendario_academico';

    protected $guarded = ['id'];

    /**
     * Rutas en disco del PDF de esos niveles, con lock (serializa con un guardado concurrente que reemplace el archivo).
     * Quien borra niveles o trámites las reúne ANTES del borrado: después, el cascade ya se llevó las filas.
     *
     * @param  iterable<int>  $nivelIds
     * @return list<string>
     */
    public static function rutasDe(iterable $nivelIds): array
    {
        return static::whereIn('escuela_nivel_id', $nivelIds)->lockForUpdate()->get()
            ->pluck('archivo_path')->filter()->values()->all();
    }
}
