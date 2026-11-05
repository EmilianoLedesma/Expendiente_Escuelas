<?php

namespace App\Models;

use App\Models\Scopes\EscuelaNoArchivada;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected $casts = ['detalle' => 'array', 'created_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * El objeto afectado según objeto_tipo (se lee en vivo: la bitácora no guarda nombres, y si se
     * purgó el objeto la relación viene vacía). Las tres se cargan juntas y se usa la que toca.
     *
     * @return BelongsTo<Escuela, $this>
     */
    public function escuelaObjeto(): BelongsTo
    {
        return $this->belongsTo(Escuela::class, 'objeto_id')->withoutGlobalScope(EscuelaNoArchivada::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuarioObjeto(): BelongsTo
    {
        return $this->belongsTo(User::class, 'objeto_id');
    }

    /** @return BelongsTo<Plantel, $this> */
    public function plantelObjeto(): BelongsTo
    {
        return $this->belongsTo(Plantel::class, 'objeto_id');
    }
}
