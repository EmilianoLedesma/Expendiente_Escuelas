<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property int $sala_id
 * @property int $cantidad_alumnos
 */
class MatriculaSala extends Model
{
    public $timestamps = false;

    protected $table = 'matricula_salas';

    protected $fillable = ['escuela_nivel_id', 'sala_id', 'cantidad_alumnos'];
}
