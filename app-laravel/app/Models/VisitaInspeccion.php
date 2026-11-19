<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property int $numero
 * @property string $estado programada|realizada
 * @property string|null $resultado cumple|con_observaciones
 * @property string|null $acta_path
 * @property string|null $acta_nombre
 * @property Carbon $programada_para
 * @property string $inspector
 * @property Carbon|null $realizada_el
 * @property-read Collection<int, ObservacionVisita> $observaciones
 * @property-read Collection<int, RespuestaVisita> $respuestas
 * @property Carbon|null $subsanar_hasta
 * @property-read EscuelaNivel $nivel
 */
class VisitaInspeccion extends Model
{
    protected $table = 'visitas_inspeccion';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'programada_para' => 'datetime',
            'realizada_el' => 'date',
            'subsanar_hasta' => 'date',
        ];
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class, 'escuela_nivel_id');
    }

    /** @return HasMany<ObservacionVisita, $this> */
    public function observaciones(): HasMany
    {
        return $this->hasMany(ObservacionVisita::class, 'visita_id')->orderBy('orden');
    }

    /** @return HasMany<RespuestaVisita, $this> */
    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaVisita::class, 'visita_id')->orderBy('id');
    }
}
