<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Typed data captured with the upload (ADR-007). Contains PII; not
 * encrypted yet (owner decision).
 *
 * @property int $documento_plantel_id
 * @property string $calle
 * @property string|null $numero_ext
 * @property string $colonia
 * @property string $municipio
 * @property string $codigo_postal
 */
class CertificadoNumeroOficial extends Model
{
    public $timestamps = false;

    protected $table = 'certificados_numero_oficial';

    protected $primaryKey = 'documento_plantel_id';

    public $incrementing = false;

    protected $fillable = [
        'documento_plantel_id',
        'calle',
        'numero_ext',
        'colonia',
        'municipio',
        'codigo_postal',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoPlantel::class, 'documento_plantel_id');
    }
}
