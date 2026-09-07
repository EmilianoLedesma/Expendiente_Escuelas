<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
