<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Escuela extends Model
{
    protected $fillable = [
        'plantel_id',
        'nombre_aprobado',
    ];

    public function plantel()
    {
        return $this->belongsTo(Plantel::class);
    }
}
