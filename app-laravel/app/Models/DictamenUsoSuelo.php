<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $documento_plantel_id
 * @property string|null $autoridad_expide
 * @property string|null $folio_dictamen
 * @property string|null $giro_autorizado
 */
class DictamenUsoSuelo extends Model
{
    public $timestamps = false;

    protected $table = 'dictamenes_uso_suelo';

    protected $primaryKey = 'documento_plantel_id';

    public $incrementing = false;

    protected $fillable = [
        'documento_plantel_id',
        'autoridad_expide',
        'folio_dictamen',
        'giro_autorizado',
    ];

    public function documentoPlantel(): BelongsTo
    {
        return $this->belongsTo(DocumentoPlantel::class);
    }
}
