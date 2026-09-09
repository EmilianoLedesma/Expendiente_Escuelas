<?php

namespace App\Models;

use Database\Factories\SolicitanteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Solicitante extends Model
{
    /** @use HasFactory<SolicitanteFactory> */
    use HasFactory;

    protected $fillable = ['user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function escuelas(): HasMany
    {
        return $this->hasMany(Escuela::class);
    }

    /**
     * Crea un `solicitante` para cada `user` que todavía no tenga uno.
     * La llama la migración que crea `solicitantes` (corre siempre que
     * corren las migraciones, en cualquier entorno — spec §1b) y, de
     * forma independiente, la suite de tests — para no depender de qué
     * migración es "la más reciente" al simular un rollback.
     */
    public static function backfillDesdeUsers(): void
    {
        DB::statement(
            'INSERT INTO solicitantes (user_id, created_at, updated_at)
             SELECT id, now(), now() FROM users
             WHERE id NOT IN (SELECT user_id FROM solicitantes)'
        );
    }
}
