<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $clave
 * @property string $nombre
 * @property string $aplica_persona
 * @property string $ambito
 * @property int|null $vigencia_max_dias
 */
class TipoDocumento extends Model
{
    protected $table = 'tipos_documentos';

    public $timestamps = true;
}
