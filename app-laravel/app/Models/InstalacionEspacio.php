<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $plantel_id
 * @property int $tipo_espacio_id
 * @property int|null $cantidad
 * @property string|null $superficie_m2
 * @property int|null $capacidad_promedio
 * @property bool|null $ventilacion_natural
 * @property bool|null $iluminacion_natural
 * @property string|null $destinado_a
 */
class InstalacionEspacio extends Model
{
    protected $table = 'instalaciones_espacios';

    protected $fillable = [
        'plantel_id',
        'tipo_espacio_id',
        'cantidad',
        'superficie_m2',
        'capacidad_promedio',
        'ventilacion_natural',
        'iluminacion_natural',
        'destinado_a',
    ];

    protected $casts = [
        'ventilacion_natural' => 'boolean',
        'iluminacion_natural' => 'boolean',
    ];

    public function plantel(): BelongsTo
    {
        return $this->belongsTo(Plantel::class);
    }

    public function tipoEspacio(): BelongsTo
    {
        return $this->belongsTo(TipoEspacio::class);
    }
}
