<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 */
class Asignatura extends Model
{
    /**
     * The one asignatura the capacity engine reads (secundaria.personal.educacion_fisica,
     * ADR-010 P10). AsignaturasSeeder writes the row from this constant and
     * ConstruirDatosCapacidad looks it up through it, so a rewording changes both at once.
     */
    public const EDUCACION_FISICA = 'Educación Física';

    public $timestamps = false;

    protected $table = 'asignaturas';
}
