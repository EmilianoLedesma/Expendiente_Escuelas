<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $respuesta_id
 * @property string $archivo_path
 * @property string $archivo_nombre
 */
class RespuestaVisitaArchivo extends Model
{
    protected $table = 'respuestas_visita_archivos';

    public $timestamps = false;

    protected $guarded = [];
}
