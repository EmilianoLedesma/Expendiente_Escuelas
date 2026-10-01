<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $nivel_educativo_id
 * @property string $nombre
 * @property bool $requiere_asignatura
 * @property bool $requiere_sala
 */
class CargoPuesto extends Model
{
    public $timestamps = false;

    protected $table = 'cargos_puestos';

    protected function casts(): array
    {
        return ['requiere_asignatura' => 'boolean', 'requiere_sala' => 'boolean'];
    }
}
