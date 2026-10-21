<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Decisión vigente del revisor sobre un paso de un escuela_nivel (clave compuesta nivel + paso).
 *
 * @property int $escuela_nivel_id
 * @property string $paso_clave
 * @property string $estado
 * @property string|null $motivo
 * @property int $decidido_por
 * @property string $decidido_at
 */
class DecisionRevision extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'decisiones_revision';

    /** La PK real es (escuela_nivel_id, paso_clave); Eloquent no soporta claves compuestas: leer con where() y escribir con DB::table()->upsert(), no con find/save. */
    protected $primaryKey = 'escuela_nivel_id';

    protected $fillable = ['escuela_nivel_id', 'paso_clave', 'estado', 'motivo', 'decidido_por', 'decidido_at'];

    public function escuelaNivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class);
    }
}
