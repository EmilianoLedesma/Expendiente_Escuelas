<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $plantel_id
 * @property int $solicitante_id
 * @property string|null $nombre_aprobado
 */
class Escuela extends Model
{
    protected $fillable = [
        'plantel_id',
        'solicitante_id',
        'nombre_aprobado',
    ];

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

    public function ternasNombres(): HasMany
    {
        return $this->hasMany(TernaNombre::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoEscuela::class);
    }
}
