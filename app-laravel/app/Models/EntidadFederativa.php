<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $clave
 * @property string $nombre
 * @property string|null $abreviatura
 */
class EntidadFederativa extends Model
{
    public $timestamps = false;

    protected $table = 'entidades_federativas';

    protected $fillable = ['clave', 'nombre', 'abreviatura'];

    /** @return HasMany<Municipio, $this> */
    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class, 'entidad_federativa_id');
    }
}
