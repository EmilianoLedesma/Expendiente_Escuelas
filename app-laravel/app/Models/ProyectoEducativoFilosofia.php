<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $mision
 * @property string|null $vision
 * @property string|null $valores
 */
class ProyectoEducativoFilosofia extends Model
{
    protected $table = 'proyecto_educativo_filosofia';

    protected $guarded = ['id'];
}
