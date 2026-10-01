<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 */
class Asignatura extends Model
{
    public $timestamps = false;

    protected $table = 'asignaturas';
}
