<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sección 7 (Currículum Ampliado): por ámbito, `<ambito>_trabaja` (bool|null) y `<ambito>_justificacion` (string|null).
 *
 * @property int $id
 * @property int $escuela_nivel_id
 */
class ProyectoEducativoCurriculum extends Model
{
    protected $table = 'proyecto_educativo_curriculum';

    protected $guarded = ['id'];
}
