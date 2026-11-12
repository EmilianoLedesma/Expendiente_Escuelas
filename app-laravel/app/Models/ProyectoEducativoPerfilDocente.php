<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $habilidades_virtudes
 */
class ProyectoEducativoPerfilDocente extends Model
{
    protected $table = 'proyecto_educativo_perfil_docente';

    protected $guarded = ['id'];
}
