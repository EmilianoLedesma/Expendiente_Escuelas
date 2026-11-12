<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $perfil_egreso
 */
class ProyectoEducativoEgreso extends Model
{
    protected $table = 'proyecto_educativo_egreso';

    protected $guarded = ['id'];
}
