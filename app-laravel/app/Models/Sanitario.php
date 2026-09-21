<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $plantel_id
 * @property string $categoria
 * @property int|null $cantidad_retretes
 * @property int|null $cantidad_mingitorios
 * @property int|null $cantidad_lavabos
 * @property string|null $superficie_m2
 * @property bool|null $ventilacion_natural
 * @property bool|null $iluminacion_natural
 */
class Sanitario extends Model
{
    const UPDATED_AT = null;

    protected $table = 'sanitarios';

    protected $fillable = [
        'plantel_id',
        'categoria',
        'cantidad_retretes',
        'cantidad_mingitorios',
        'cantidad_lavabos',
        'superficie_m2',
        'ventilacion_natural',
        'iluminacion_natural',
    ];

    protected $casts = [
        'ventilacion_natural' => 'boolean',
        'iluminacion_natural' => 'boolean',
    ];

    public function plantel(): BelongsTo
    {
        return $this->belongsTo(Plantel::class);
    }
}
