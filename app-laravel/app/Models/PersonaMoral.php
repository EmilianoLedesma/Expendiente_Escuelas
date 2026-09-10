<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonaMoral extends Model
{
    public $timestamps = false;

    protected $table = 'personas_morales';

    protected $primaryKey = 'responsable_legal_id';

    public $incrementing = false;

    protected $fillable = [
        'responsable_legal_id',
        'razon_social',
        'numero_escritura_constitutiva',
        'fecha_escritura_constitutiva',
        'notario_nombre',
        'notario_numero',
        'notario_ciudad',
        'folio_registro_publico',
        'fecha_inscripcion_rpp',
        'nombre_representante_legal',
    ];

    public function responsableLegal(): BelongsTo
    {
        return $this->belongsTo(ResponsableLegal::class, 'responsable_legal_id');
    }
}
