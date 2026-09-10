<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResponsableLegal extends Model
{
    protected $table = 'responsables_legales';

    protected $fillable = [
        'escuela_id',
        'tipo_persona',
        'domicilio_notificaciones',
        'persona_autorizada_recoger',
    ];

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    public function personaFisica(): HasOne
    {
        return $this->hasOne(PersonaFisica::class);
    }

    public function personaMoral(): HasOne
    {
        return $this->hasOne(PersonaMoral::class);
    }

    public function gestor(): HasOne
    {
        return $this->hasOne(Gestor::class);
    }
}
