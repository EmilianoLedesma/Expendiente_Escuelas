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
    ];

    public function documentoPlantel(): BelongsTo
    {
        return $this->belongsTo(DocumentoPlantel::class);
    }
}
