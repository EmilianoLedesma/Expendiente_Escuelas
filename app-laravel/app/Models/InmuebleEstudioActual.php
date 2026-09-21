<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tipos de estudios que el inmueble ya imparte (Anexo 2).
 * nivel_educativo_id es NULL cuando el solicitante eligió "otro" y llenó
 * otro_nivel_texto.
 *
 * @property int $id
 * @property int $plantel_id
 * @property int|null $nivel_educativo_id
 * @property string|null $otro_nivel_texto
 * @property int $numero_alumnos
 */
class InmuebleEstudioActual extends Model
{
    const UPDATED_AT = null;

    protected $table = 'inmueble_estudios_actuales';

    protected $fillable = [
        'plantel_id',
        'nivel_educativo_id',
        'otro_nivel_texto',
        'numero_alumnos',
    ];

    public function plantel(): BelongsTo
    {
        return $this->belongsTo(Plantel::class);
    }

    public function nivelEducativo(): BelongsTo
    {
        return $this->belongsTo(NivelEducativo::class);
    }
}
