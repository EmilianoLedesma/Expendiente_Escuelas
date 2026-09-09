<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gestor extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gestores';

    protected $primaryKey = 'responsable_legal_id';

    public $incrementing = false;

    protected $fillable = [
        'responsable_legal_id',
        'nombre',
        'numero_poder',
        'notario_nombre',
        'notario_numero',
        'fecha_poder',
    ];

    public function responsableLegal(): BelongsTo
    {
        return $this->belongsTo(ResponsableLegal::class, 'responsable_legal_id');
    }
}
