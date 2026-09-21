<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property int $concepto_id
 * @property int $cantidad_declarada
 */
class MobiliarioNivel extends Model
{
    const UPDATED_AT = null;

    protected $table = 'mobiliario_nivel';

    protected $fillable = [
        'escuela_nivel_id',
        'concepto_id',
        'cantidad_declarada',
    ];

    public function escuelaNivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class);
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(MobiliarioConcepto::class, 'concepto_id');
    }
}
