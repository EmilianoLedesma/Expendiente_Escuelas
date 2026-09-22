<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $responsable_legal_id
 * @property string $nombre
 * @property string|null $fecha_nacimiento
 * @property string|null $rfc
 * @property string|null $curp
 */
class PersonaFisica extends Model
{
    public $timestamps = false;

    protected $table = 'personas_fisicas';

    protected $primaryKey = 'responsable_legal_id';

    public $incrementing = false;

    protected $fillable = [
        'responsable_legal_id',
        'nombre',
        'fecha_nacimiento',
        'rfc',
        'curp',
    ];

    public function responsableLegal(): BelongsTo
    {
        return $this->belongsTo(ResponsableLegal::class, 'responsable_legal_id');
    }
}
