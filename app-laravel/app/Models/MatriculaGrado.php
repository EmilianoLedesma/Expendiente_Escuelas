<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property int $grado_id
 * @property string $grupo
 * @property int $cantidad_alumnos
 */
class MatriculaGrado extends Model
{
    public $timestamps = false;

    protected $table = 'matricula_grados';

    protected $fillable = ['escuela_nivel_id', 'grado_id', 'grupo', 'cantidad_alumnos'];
}
