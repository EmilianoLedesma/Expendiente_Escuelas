<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $perfil_ingreso
 * @property string|null $mecanismo_ingreso
 * @property string|null $costo_usuario
 * @property string|null $instrumentos_evaluacion
 */
class ProyectoEducativoIngreso extends Model
{
    protected $table = 'proyecto_educativo_ingreso';

    protected $guarded = ['id'];
}
