<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $plantel_id
 * @property int $tipo_documento_id
 * @property string $archivo_path
 * @property string|null $fecha_emision
 * @property string|null $fecha_vigencia
 * @property string $estado_validacion
 */
class DocumentoPlantel extends Model
{
    protected $table = 'documentos_plantel';

    protected $fillable = [
        'plantel_id',
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

    public function plantel(): BelongsTo
    {
        return $this->belongsTo(Plantel::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    /** @return HasOne<ConstanciaSeguridadEstructural, $this> */
    public function constanciaSeguridadEstructural(): HasOne
    {
        return $this->hasOne(ConstanciaSeguridadEstructural::class);
    }

    /** @return HasOne<AcreditacionOcupacionLegal, $this> */
    public function acreditacionOcupacionLegal(): HasOne
    {
        return $this->hasOne(AcreditacionOcupacionLegal::class);
    }
}
