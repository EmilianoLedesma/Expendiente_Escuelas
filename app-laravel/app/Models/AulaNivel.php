<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property int $numero_aulas
 * @property string|null $superficie_m2
 */
class AulaNivel extends Model
{
    const UPDATED_AT = null;

    protected $table = 'aulas_nivel';

    protected $fillable = [
        'escuela_nivel_id',
        'numero_aulas',
        'superficie_m2',
    ];

    public function escuelaNivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class);
    }
}
