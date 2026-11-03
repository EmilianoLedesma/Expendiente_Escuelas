<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Bitácora append-only: solo se inserta (ver RegistrarAccion).
 *
 * @property int $id
 * @property int $actor_id
 * @property string $accion
 * @property string $objeto_tipo
 * @property int $objeto_id
 * @property string|null $motivo
 * @property array<string, mixed> $detalle
 * @property Carbon $created_at
 */
class BitacoraAccion extends Model
{
    protected $table = 'bitacora_acciones';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['detalle' => 'array'];
}
