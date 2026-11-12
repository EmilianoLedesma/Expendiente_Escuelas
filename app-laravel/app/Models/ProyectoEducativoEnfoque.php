<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $propuesta_global
 * @property string|null $apropiacion_enfoque
 * @property list<string>|null $estrategias_centrales
 * @property list<string>|null $actividades_sustantivas
 */
class ProyectoEducativoEnfoque extends Model
{
    protected $table = 'proyecto_educativo_enfoque';

    protected $guarded = ['id'];

    protected $casts = ['estrategias_centrales' => 'array', 'actividades_sustantivas' => 'array'];
}
