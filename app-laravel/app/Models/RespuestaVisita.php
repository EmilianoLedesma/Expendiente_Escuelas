<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $visita_id
 * @property string $nota
 * @property int $creada_por
 * @property Carbon $created_at
 * @property-read Collection<int, RespuestaVisitaArchivo> $archivos
 */
class RespuestaVisita extends Model
{
    protected $table = 'respuestas_visita';

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return HasMany<RespuestaVisitaArchivo, $this> */
    public function archivos(): HasMany
    {
        return $this->hasMany(RespuestaVisitaArchivo::class, 'respuesta_id');
    }
}
