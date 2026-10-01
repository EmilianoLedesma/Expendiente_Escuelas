<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Typed data captured with the upload (ADR-007). Contains PII; not
 * encrypted yet (owner decision).
 *
 * @property int $documento_escuela_id
 * @property string $nombre
 * @property string $curp
 */
class ConstanciaCurp extends Model
{
    public $timestamps = false;

    protected $table = 'constancias_curp';

    protected $primaryKey = 'documento_escuela_id';

    public $incrementing = false;

    protected $fillable = [
        'documento_escuela_id',
        'nombre',
        'curp',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoEscuela::class, 'documento_escuela_id');
    }
}
