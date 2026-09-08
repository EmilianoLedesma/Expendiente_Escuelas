<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $calle
 * @property string|null $numero_ext
 * @property string|null $numero_int
 * @property string $colonia
 * @property string|null $localidad
 * @property string $municipio
 * @property string $codigo_postal
 * @property string|null $telefono
 * @property string|null $correo_electronico
 */
class Plantel extends Model
{
    protected $table = 'planteles';

    protected $fillable = [
        'calle',
        'numero_ext',
        'numero_int',
        'colonia',
        'localidad',
        'municipio',
        'codigo_postal',
        'telefono',
        'correo_electronico',
    ];
}
