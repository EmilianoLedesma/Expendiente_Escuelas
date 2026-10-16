<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Prioridad fijada por el supervisor a un escuela_nivel; sin fila se lee como media.
 *
 * @property int $escuela_nivel_id
 * @property string $prioridad
 * @property int|null $actualizado_por
 * @property Carbon $updated_at
 */
class PrioridadRevision extends Model
{
    public const CREATED_AT = null;

    public $incrementing = false;

    protected $table = 'prioridades_revision';

    protected $primaryKey = 'escuela_nivel_id';

    protected $fillable = ['escuela_nivel_id', 'prioridad', 'actualizado_por'];

    public function escuelaNivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class);
    }
}
