<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acceso de un usuario con rol `responsable_nivel` a UN escuela_nivel (ADR-015).
 *
 * @property int $id
 * @property int $user_id
 * @property int $escuela_nivel_id
 * @property int $invitado_por_solicitante_id
 * @property-read User $user
 * @property-read EscuelaNivel $escuelaNivel
 */
class ResponsableNivel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'responsables_nivel';

    protected $fillable = ['user_id', 'escuela_nivel_id', 'invitado_por_solicitante_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function escuelaNivel(): BelongsTo
    {
        return $this->belongsTo(EscuelaNivel::class);
    }

    public function invitadoPor(): BelongsTo
    {
        return $this->belongsTo(Solicitante::class, 'invitado_por_solicitante_id');
    }
}
