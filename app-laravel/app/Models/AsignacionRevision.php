<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Revisor asignado a un escuela_nivel en revisión (HUD SEDEQ). Las reglas viven en App\Application\Revision.
 *
 * @property int $escuela_nivel_id
 * @property int $revisor_id
 * @property int $asignado_por
 * @property Carbon $asignado_at
 */
class AsignacionRevision extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'asignaciones_revision';

    protected $primaryKey = 'escuela_nivel_id';

    protected $fillable = ['escuela_nivel_id', 'revisor_id', 'asignado_por', 'asignado_at'];

    protected $casts = ['asignado_at' => 'datetime'];

    public function escuelaNivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }
}
