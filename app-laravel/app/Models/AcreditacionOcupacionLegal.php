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
        'fecha_escritura',
        'notario_nombre',
        'notario_numero',
        'notario_localidad',
        'notario_entidad_federativa_id',
        'notario_municipio_id',
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
        'propietario_nombre',
        'fecha_inicio_vigencia',
        'vigencia_indeterminada',
        'arrendador_es_propietario',
        'facultades_referencia',
        'ratificacion_fecha',
        'ratificacion_notario_nombre',
        'ratificacion_notaria_numero',
        'otro_referencia',
        'otro_titular',
        'otro_fecha_expedicion',
        'otro_autoridad',
        'otro_derecho',
    ];

    protected $casts = [
        'vigencia_indeterminada' => 'boolean',
        'arrendador_es_propietario' => 'boolean',
    ];

    public function documentoPlantel(): BelongsTo
    {
        return $this->belongsTo(DocumentoPlantel::class);
    }
}
