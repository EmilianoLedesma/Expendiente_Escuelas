<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string $tipo_academia
 * @property string|null $descripcion_academia
 * @property string $justificacion_academia
 */
class ProyectoEducativoAcademias extends Model
{
    protected $table = 'proyecto_educativo_academias';

    protected $guarded = ['id'];
}
