<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $criterios_acreditacion
 * @property string|null $opciones_acreditacion
 * @property string|null $criterios_promocion
 * @property string|null $criterios_titulacion
 * @property string|null $servicios_control_escolar
 */
class ProyectoEducativoCriterios extends Model
{
    protected $table = 'proyecto_educativo_criterios';

    protected $guarded = ['id'];
}
