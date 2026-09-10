<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de solo lectura — nada en este proyecto escribe niveles_educativos
 * fuera de CatalogoMinimoSeeder.
 */
class NivelEducativo extends Model
{
    protected $table = 'niveles_educativos';

    public $timestamps = false;

    protected $fillable = [];

    /** Los 4 niveles de Educación Básica que Paso 2.3 permite seleccionar (PRD §5). */
    public function scopeEducacionBasica(Builder $query): Builder
    {
        return $query
            ->whereIn('clave', ['inicial', 'preescolar', 'primaria', 'secundaria'])
            ->orderBy('orden');
    }
}
