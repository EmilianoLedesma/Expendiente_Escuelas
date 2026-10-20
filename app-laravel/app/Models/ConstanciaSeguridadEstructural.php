<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $documento_plantel_id
 * @property string|null $perito_nombre
 * @property string|null $perito_cedula_profesional
 * @property string|null $perito_registro_dro
 * @property string|null $perito_registro_autoridad
 * @property string|null $perito_registro_vigencia
 * @property string|null $vigencia_constancia
 * @property string|null $calidad_profesional dro | corresponsable_seguridad_estructural | otra; NULL = constancia anterior a este dato
 * @property string|null $calidad_profesional_otra
 * @property string|null $folio_constancia
 * @property string $tipo_vigencia fecha_expresa | sin_fecha_expresa | no_capturada
 * @property string|null $perito_registro_fecha fecha de registro o refrendo declarada
 * @property string|null $perito_registro_vencimiento vencimiento del registro declarado
 *
 * perito_registro_dro guarda el número de registro profesional (DRO o Corresponsable); perito_registro_vigencia es
 * histórica y de semántica no determinada: ninguna regla la evalúa.
 */
class ConstanciaSeguridadEstructural extends Model
{
    public $timestamps = false;

    protected $table = 'constancias_seguridad_estructural';

    protected $primaryKey = 'documento_plantel_id';

    public $incrementing = false;

    protected $fillable = [
        'documento_plantel_id',
        'perito_nombre',
        'perito_cedula_profesional',
        'perito_registro_dro',
        'perito_registro_autoridad',
        'perito_registro_vigencia',
        'vigencia_constancia',
        'calidad_profesional',
        'calidad_profesional_otra',
        'folio_constancia',
        'tipo_vigencia',
        'perito_registro_fecha',
        'perito_registro_vencimiento',
    ];

    public function documentoPlantel(): BelongsTo
    {
        return $this->belongsTo(DocumentoPlantel::class);
    }
}
