<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $visita_id
 * @property int $orden
 * @property string $texto
 */
class ObservacionVisita extends Model
{
    protected $table = 'observaciones_visita';

    public $timestamps = false;

    protected $guarded = [];
}
