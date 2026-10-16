<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avance de revisión de un escuela_nivel; sobrevive a la desasignación.
 *
 * @property int $escuela_nivel_id
 * @property int $secciones_revisadas
 * @property int $secciones_total
 */
class ProgresoRevision extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'progreso_revision';

    protected $primaryKey = 'escuela_nivel_id';

    protected $fillable = ['escuela_nivel_id', 'secciones_revisadas', 'secciones_total'];

    public function escuelaNivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class);
    }
}
