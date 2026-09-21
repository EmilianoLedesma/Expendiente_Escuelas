<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catálogo de solo lectura — nada escribe mobiliario_conceptos fuera de
 * MobiliarioConceptosSeeder. sala_id es NULL para los conceptos de la Sala
 * de Usos Múltiples, que no es una sala por grupo de edad.
 *
 * @property int $id
 * @property int|null $sala_id
 * @property string $nombre
 * @property string $tipo_ratio
 * @property string $valor_ratio
 * @property string|null $fuente
 */
class MobiliarioConcepto extends Model
{
    protected $table = 'mobiliario_conceptos';

    public $timestamps = false;

    protected $fillable = [];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }
}
