<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string $formacion_laboral_1
 * @property string $formacion_laboral_2
 * @property string|null $otras_formaciones
 */
class ProyectoEducativoFormacionLaboral extends Model
{
    protected $table = 'proyecto_educativo_formacion_laboral';

    protected $guarded = ['id'];
}
