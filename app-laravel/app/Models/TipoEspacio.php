<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de solo lectura — nada escribe tipos_espacios fuera de
 * TiposEspaciosSeeder.
 *
 * @property int $id
 * @property string $clave
 * @property string $nombre
 * @property string $categoria
 * @property bool $permite_campo_futbol
 * @property bool $permite_material_biblioteca
 */
class TipoEspacio extends Model
{
    protected $table = 'tipos_espacios';

    public $timestamps = false;

    protected $fillable = [];

    protected $casts = [
        'permite_campo_futbol' => 'boolean',
        'permite_material_biblioteca' => 'boolean',
    ];
}
