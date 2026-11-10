<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $objetivo_general
 * @property string|null $objetivos_especificos
 */
class ProyectoEducativoObjetivos extends Model
{
    protected $table = 'proyecto_educativo_objetivos';

    protected $guarded = ['id'];
}
