<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entidad_federativa_id
 * @property int $clave
 * @property string $nombre
 */
class Municipio extends Model
{
    public $timestamps = false;

    protected $table = 'municipios';

    protected $fillable = ['entidad_federativa_id', 'clave', 'nombre'];

    /** @return BelongsTo<EntidadFederativa, $this> */
    public function entidadFederativa(): BelongsTo
    {
        return $this->belongsTo(EntidadFederativa::class, 'entidad_federativa_id');
    }
}
