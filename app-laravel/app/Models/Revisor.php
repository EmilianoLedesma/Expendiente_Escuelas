<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Persona invitada alguna vez como revisor; revocado_at no nulo = revisor revocado.
 *
 * @property int $user_id
 * @property Carbon|null $revocado_at
 */
class Revisor extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'revisores';

    protected $primaryKey = 'user_id';

    protected $fillable = ['user_id', 'revocado_at'];

    protected $casts = ['revocado_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
