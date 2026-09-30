<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contains PII (holder name, CURP) — same data class as personas_fisicas.
 * See PENDIENTE-motor-validacion-hechos P4 for retention/encryption.
 *
 * @property int $id
 * @property int $escuela_id
 * @property int $tipo_documento_id
 * @property string $archivo_path
 * @property string $tipo_hecho
 * @property string $valor
 * @property string $metodo
 */
class HechoDocumento extends Model
{
    protected $table = 'hechos_documento';

    protected $fillable = [
        'escuela_id',
        'tipo_documento_id',
        'archivo_path',
        'tipo_hecho',
        'valor',
        'metodo',
    ];

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }
}
