<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $evaluacion_diagnostica
 * @property string|null $evaluacion_formativa
 * @property string|null $evaluacion_sumativa
 * @property string|null $heteroevaluacion
 * @property string|null $coevaluacion
 * @property string|null $autoevaluacion
 * @property string|null $elementos_porcentajes
 */
class ProyectoEducativoEvaluacion extends Model
{
    protected $table = 'proyecto_educativo_evaluacion';

    protected $guarded = ['id'];
}
