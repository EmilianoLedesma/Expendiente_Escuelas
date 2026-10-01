<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_id
 * @property string $archivo_path
 * @property bool $lista_para_envio
 * @property array<int, array<string, mixed>> $resultados
 * @property Carbon $created_at
 */
class EvaluacionValidacion extends Model
{
    const UPDATED_AT = null;

    protected $table = 'evaluaciones_validacion';

    protected $fillable = [
        'escuela_id',
        'archivo_path',
        'lista_para_envio',
        'resultados',
    ];

    protected function casts(): array
    {
        return [
            'lista_para_envio' => 'boolean',
            'resultados' => 'array',
        ];
    }

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }
}
