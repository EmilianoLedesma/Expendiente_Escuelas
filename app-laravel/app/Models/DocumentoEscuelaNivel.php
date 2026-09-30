<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Documento de Paso 2.4 (ámbito escuela_nivel, WS-5b).
 *
 * @property int $id
 * @property int $escuela_nivel_id
 * @property int $tipo_documento_id
 * @property string|null $archivo_path
 * @property string|null $fecha_emision
 * @property string|null $fecha_vigencia
 * @property string $estado_validacion
 * @property Carbon|null $updated_at
 * @property-read ReciboPagoDerechos|null $reciboPagoDerechos
 */
class DocumentoEscuelaNivel extends Model
{
    protected $table = 'documentos_escuela_nivel';

    protected $fillable = [
        'escuela_nivel_id',
        'tipo_documento_id',
        'archivo_path',
        'fecha_emision',
        'fecha_vigencia',
        'estado_validacion',
        'observaciones',
    ];

    protected $attributes = [
        'estado_validacion' => 'pendiente',
    ];

    public function escuelaNivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    /** @return HasOne<ReciboPagoDerechos, $this> */
    public function reciboPagoDerechos(): HasOne
    {
        return $this->hasOne(ReciboPagoDerechos::class);
    }
}
