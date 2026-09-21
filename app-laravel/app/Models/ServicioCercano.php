<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instituciones de salud/emergencia cercanas al inmueble (Anexo 2).
 * Ancladas a plantel_id: son del inmueble, no del nivel.
 *
 * @property int $id
 * @property int $plantel_id
 * @property string $nombre
 * @property string $tipo
 * @property bool|null $es_publico
 * @property string|null $distancia_valor
 * @property string|null $distancia_unidad
 */
class ServicioCercano extends Model
{
    const UPDATED_AT = null;

    protected $table = 'servicios_cercanos';

    protected $fillable = [
        'plantel_id',
        'nombre',
        'tipo',
        'es_publico',
        'distancia_valor',
        'distancia_unidad',
    ];

    protected $casts = [
        'es_publico' => 'boolean',
    ];

    public function plantel(): BelongsTo
    {
        return $this->belongsTo(Plantel::class);
    }
}
