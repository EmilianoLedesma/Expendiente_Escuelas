<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
