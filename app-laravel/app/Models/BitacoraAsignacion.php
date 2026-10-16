<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de asignar / reasignar / desasignar (sobrecarga o revocación).
 *
 * @property int $id
 * @property int $escuela_nivel_id
 * @property int|null $revisor_id
 * @property string $accion
 * @property int $actor_id
 * @property Carbon $created_at
 */
class BitacoraAsignacion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'bitacora_asignaciones';

    protected $fillable = ['escuela_nivel_id', 'revisor_id', 'accion', 'actor_id'];

    public function escuelaNivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class);
    }
}
