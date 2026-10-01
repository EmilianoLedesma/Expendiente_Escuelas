<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Title count of an uploaded acervo bibliográfico relation (ADR-007, WS-5b).
 *
 * @property int $documento_escuela_nivel_id
 * @property int $numero_titulos
 */
class RelacionAcervoBibliografico extends Model
{
    public $timestamps = false;

    protected $table = 'relaciones_acervo_bibliografico';

    protected $primaryKey = 'documento_escuela_nivel_id';

    public $incrementing = false;

    protected $fillable = [
        'documento_escuela_nivel_id',
        'numero_titulos',
    ];

    protected $casts = [
        'numero_titulos' => 'integer',
    ];

    public function documentoEscuelaNivel(): BelongsTo
    {
        return $this->belongsTo(DocumentoEscuelaNivel::class);
    }
}
