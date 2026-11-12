<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $diagnostico
 * @property string|null $hoja_ruta_path
 * @property string|null $hoja_ruta_nombre
 * @property int|null $hoja_ruta_paginas
 * @property string|null $planeacion_path
 * @property string|null $planeacion_nombre
 * @property int|null $planeacion_paginas
 */
class ProyectoEducativoPaec extends Model
{
    protected $table = 'proyecto_educativo_paec';

    protected $guarded = ['id'];

    /**
     * Rutas en disco de los PDF de esos niveles, con lock (serializa con un guardado concurrente que reemplace un archivo).
     * Quien borra niveles o trámites las reúne ANTES del borrado: después, el cascade ya se llevó las filas.
     *
     * @param  iterable<int>  $nivelIds
     * @return list<string>
     */
    public static function rutasDe(iterable $nivelIds): array
    {
        return static::whereIn('escuela_nivel_id', $nivelIds)->lockForUpdate()->get()
            ->flatMap(fn (self $fila) => [$fila->hoja_ruta_path, $fila->planeacion_path])
            ->filter()->values()->all();
    }
}
