<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una decisión del revisor ya tomada; el historial solo se inserta, nunca se actualiza.
 *
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string $paso_clave
 * @property string $estado
 * @property string|null $motivo
 * @property int $decidido_por
 * @property string $decidido_at
 */
class HistorialDecisionRevision extends Model
{
    public $timestamps = false;

    protected $table = 'historial_decisiones_revision';

    protected $fillable = ['escuela_nivel_id', 'paso_clave', 'estado', 'motivo', 'decidido_por', 'decidido_at'];

    public function escuelaNivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class);
    }
}
