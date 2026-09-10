<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TernaNombre extends Model
{
    const UPDATED_AT = null;

    protected $table = 'ternas_nombres';

    protected $fillable = [
        'escuela_id',
        'numero_propuesta',
        'nombre_propuesto',
        'valido_marca_comercial',
        'valido_registro_sedeq',
    ];

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }
}
