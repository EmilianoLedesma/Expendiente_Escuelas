<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $nivel_educativo_id
 * @property string $nombre
 * @property int $orden
 */
class Grado extends Model
{
    public $timestamps = false;

    protected $table = 'grados';
}
