<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anexo 1 (Plantilla de personal) row. Columns other than escuela_nivel_id
 * are nullable for resumable drafts (docs/progress.md, 2026-09-07); only
 * rows that RegistroPersonalCompleto accepts count for the capacity engine.
 *
 * @property int $id
 * @property int $escuela_nivel_id
 * @property int|null $cargo_puesto_id
 * @property string|null $nombre
 * @property string|null $nacionalidad
 * @property string|null $sexo
 * @property string|null $estudios
 * @property string|null $cedula_o_documento
 */
class Personal extends Model
{
    protected $table = 'personal';

    protected $fillable = [
        'escuela_nivel_id',
        'cargo_puesto_id',
        'nombre',
        'nacionalidad',
        'sexo',
        'estudios',
        'cedula_o_documento',
    ];

    public function escuelaNivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class);
    }

    public function cargoPuesto(): BelongsTo
    {
        return $this->belongsTo(CargoPuesto::class);
    }
}
