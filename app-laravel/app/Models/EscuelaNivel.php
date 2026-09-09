<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscuelaNivel extends Model
{
    protected $table = 'escuela_niveles';

    protected $fillable = [
        'escuela_id',
        'nivel_educativo_id',
        'estado_id',
        'folio_expediente',
        'tipo_tramite',
        'modalidad',
        'plan_estudios_referencia',
        'turno',
        'tipo_alumnado',
        'plataforma_educativa_tipo',
        'fecha_inicio_tramite',
        'fecha_resolucion',
    ];

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    public function nivelEducativo(): BelongsTo
    {
        return $this->belongsTo(NivelEducativo::class);
    }
}
