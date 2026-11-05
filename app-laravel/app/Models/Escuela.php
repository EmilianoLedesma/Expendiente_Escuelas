<?php

namespace App\Models;

use App\Models\Scopes\EscuelaNoArchivada;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $plantel_id
 * @property int $solicitante_id
 * @property string|null $nombre_aprobado
 * @property Carbon $created_at
 * @property Carbon|null $archivado_at
 * @property int|null $archivado_por
 * @property string|null $archivado_motivo
 * @property string|null $archivado_lote
 */
class Escuela extends Model
{
    protected $casts = ['archivado_at' => 'datetime'];

    protected static function booted(): void
    {
        static::addGlobalScope(new EscuelaNoArchivada);
    }

    protected $fillable = [
        'plantel_id',
        'solicitante_id',
        'nombre_aprobado',
    ];

    /** @return BelongsTo<Plantel, $this> */
    public function plantel(): BelongsTo
    {
        return $this->belongsTo(Plantel::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Solicitante::class);
    }

    public function escuelaNiveles(): HasMany
    {
        return $this->hasMany(EscuelaNivel::class);
    }

    public function responsableLegal(): HasOne
    {
        return $this->hasOne(ResponsableLegal::class);
    }

    /** @return HasMany<TernaNombre, $this> */
    public function ternasNombres(): HasMany
    {
        return $this->hasMany(TernaNombre::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoEscuela::class);
    }
}
