<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcreditacionOcupacionLegal extends Model
{
    public $timestamps = false;

    protected $table = 'acreditaciones_ocupacion_legal';

    protected $primaryKey = 'documento_plantel_id';

    public $incrementing = false;

    protected $fillable = [
        'documento_plantel_id',
        'tipo',
        'numero_escritura',
        'notario_nombre',
        'notario_numero',
        'notario_localidad',
        'folio_rpp',
        'fecha_inscripcion_rpp',
        'arrendador_comodante',
        'arrendatario_comodatario',
        'fecha_contrato',
        'vigencia_contrato',
        'uso_autorizado',
        'ratificado_notario',
        'otro_especifique',
        'observaciones',
    ];

    public function documentoPlantel(): BelongsTo
    {
        return $this->belongsTo(DocumentoPlantel::class);
    }
}
