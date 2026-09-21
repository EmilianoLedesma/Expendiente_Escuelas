<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $escuela_id
 * @property int $nivel_educativo_id
 * @property int $estado_id
 * @property string|null $folio_expediente
 * @property string $tipo_tramite
 * @property string|null $modalidad
 * @property string|null $plan_estudios_referencia
 * @property string|null $turno
 * @property string|null $tipo_alumnado
 * @property string|null $plataforma_educativa_tipo
 * @property string $fecha_inicio_tramite
 * @property string|null $fecha_resolucion
 * @property-read Escuela $escuela
 * @property-read NivelEducativo $nivelEducativo
 */
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
