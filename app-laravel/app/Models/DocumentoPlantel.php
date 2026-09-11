<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
