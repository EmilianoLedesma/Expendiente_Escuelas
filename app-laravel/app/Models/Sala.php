<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de solo lectura de las salas de Educación Inicial — nada en este
 * proyecto escribe salas fuera de CatalogoMinimoSeeder.
 *
 * @property int $id
 * @property string $clave
 * @property string $nombre
 * @property int $edad_min_meses
 * @property int $edad_max_meses
 * @property int $orden
 */
class Sala extends Model
{
    protected $table = 'salas';

    public $timestamps = false;

    protected $fillable = [];

    public function mobiliarioConceptos(): HasMany
    {
        return $this->hasMany(MobiliarioConcepto::class, 'sala_id');
    }
}
