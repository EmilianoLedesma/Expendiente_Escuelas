<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoEscuela extends Model
{
    protected $table = 'documentos_escuela';

    protected $fillable = [
        'escuela_id',
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

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }
}
